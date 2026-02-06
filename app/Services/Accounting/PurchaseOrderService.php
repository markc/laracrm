<?php

namespace App\Services\Accounting;

use App\Enums\PurchaseOrderStatus;
use App\Models\Accounting\PurchaseOrder;
use App\Models\Accounting\VendorBill;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected VendorBillService $vendorBillService
    ) {}

    public function createOrder(Customer $vendor, array $items, array $data = []): PurchaseOrder
    {
        return DB::transaction(function () use ($vendor, $items, $data) {
            $totals = $this->calculateTotals($items);

            $order = PurchaseOrder::create([
                'po_number' => $this->generatePoNumber(),
                'vendor_id' => $vendor->id,
                'order_date' => $data['order_date'] ?? now(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax'],
                'total_amount' => $totals['total'],
                'status' => PurchaseOrderStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $index => $item) {
                $itemTotal = $this->calculateItemTotal($item);
                $order->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 10,
                    'tax_amount' => $itemTotal['tax'],
                    'total_amount' => $itemTotal['total'],
                    'sort_order' => $index,
                ]);
            }

            return $order->fresh(['items', 'vendor']);
        });
    }

    public function sendOrder(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::Draft) {
            throw new \Exception('Only draft purchase orders can be sent.');
        }

        $order->update([
            'status' => PurchaseOrderStatus::Sent,
            'sent_at' => now(),
        ]);

        return $order->fresh();
    }

    public function confirmOrder(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::Sent) {
            throw new \Exception('Only sent purchase orders can be confirmed.');
        }

        $order->update([
            'status' => PurchaseOrderStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        return $order->fresh();
    }

    /**
     * Receive items against a purchase order.
     *
     * @param  array<array{item_id: int, quantity: float, location_id?: int}>  $receivedItems
     */
    public function receiveItems(PurchaseOrder $order, array $receivedItems): PurchaseOrder
    {
        if (! in_array($order->status, [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::PartiallyReceived])) {
            throw new \Exception('Only confirmed or partially received orders can receive items.');
        }

        return DB::transaction(function () use ($order, $receivedItems) {
            foreach ($receivedItems as $received) {
                $item = $order->items()->findOrFail($received['item_id']);
                $qty = (float) $received['quantity'];

                if ($qty <= 0) {
                    continue;
                }

                if ($qty > $item->remaining_quantity) {
                    throw new \Exception(
                        "Cannot receive {$qty} of '{$item->description}'. Remaining: {$item->remaining_quantity}"
                    );
                }

                $item->increment('received_quantity', $qty);

                // Create stock movement for tracked products
                if ($item->product && $item->product->track_inventory) {
                    $this->inventoryService->receiveStock(
                        product: $item->product,
                        quantity: $qty,
                        locationId: $received['location_id'] ?? null,
                        referenceType: PurchaseOrder::class,
                        referenceId: $order->id,
                        notes: "PO {$order->po_number} - {$item->description}"
                    );
                }
            }

            // Update order status
            $order->refresh();
            $order->loadMissing('items');

            if ($order->is_fully_received) {
                $order->update([
                    'status' => PurchaseOrderStatus::Received,
                    'received_at' => now(),
                ]);
            } else {
                $order->update([
                    'status' => PurchaseOrderStatus::PartiallyReceived,
                ]);
            }

            return $order->fresh(['items']);
        });
    }

    public function createBillFromOrder(PurchaseOrder $order): VendorBill
    {
        if (! in_array($order->status, [
            PurchaseOrderStatus::Confirmed,
            PurchaseOrderStatus::PartiallyReceived,
            PurchaseOrderStatus::Received,
        ])) {
            throw new \Exception('Cannot create bill from a draft, sent, or cancelled order.');
        }

        $order->loadMissing(['items', 'vendor']);

        $billItems = $order->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'description' => $item->description,
            'quantity' => $item->received_quantity > 0 ? $item->received_quantity : $item->quantity,
            'unit_price' => $item->unit_price,
            'tax_rate' => $item->tax_rate,
            'account_id' => $item->product?->expense_account_id,
        ])->toArray();

        $bill = $this->vendorBillService->createBill($order->vendor, $billItems, [
            'notes' => "Created from PO {$order->po_number}",
        ]);

        $bill->update(['purchase_order_id' => $order->id]);

        return $bill;
    }

    public function cancelOrder(PurchaseOrder $order, string $reason): PurchaseOrder
    {
        if (! in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Sent])) {
            throw new \Exception('Only draft or sent orders can be cancelled.');
        }

        $order->update([
            'status' => PurchaseOrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $order->fresh();
    }

    public function recalculateOrder(PurchaseOrder $order): PurchaseOrder
    {
        $items = $order->items->map(fn ($item) => [
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'tax_rate' => $item->tax_rate,
        ])->toArray();

        $totals = $this->calculateTotals($items);

        $order->update([
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax'],
            'total_amount' => $totals['total'],
        ]);

        return $order->fresh();
    }

    public function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $tax = 0;

        foreach ($items as $item) {
            $itemTotal = $this->calculateItemTotal($item);
            $subtotal += $itemTotal['subtotal'];
            $tax += $itemTotal['tax'];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round($subtotal + $tax, 2),
        ];
    }

    public function calculateItemTotal(array $item): array
    {
        $lineTotal = $item['quantity'] * $item['unit_price'];
        $taxRate = $item['tax_rate'] ?? 10;
        $tax = $lineTotal * ($taxRate / 100);

        return [
            'subtotal' => round($lineTotal, 2),
            'tax' => round($tax, 2),
            'total' => round($lineTotal + $tax, 2),
        ];
    }

    public function generatePoNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ym').'-';
        $lastOrder = PurchaseOrder::where('po_number', 'like', $prefix.'%')
            ->orderBy('po_number', 'desc')
            ->first();

        $nextNumber = $lastOrder
            ? (int) substr($lastOrder->po_number, -4) + 1
            : 1;

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
