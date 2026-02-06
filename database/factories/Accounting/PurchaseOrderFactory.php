<?php

namespace Database\Factories\Accounting;

use App\Enums\PurchaseOrderStatus;
use App\Models\Accounting\PurchaseOrder;
use App\Models\CRM\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    protected static int $poNumber = 1000;

    public function definition(): array
    {
        $orderDate = fake()->dateTimeBetween('-3 months', 'now');
        $subtotal = fake()->randomFloat(2, 500, 20000);
        $taxAmount = round($subtotal * 0.10, 2);
        $totalAmount = $subtotal + $taxAmount;

        return [
            'po_number' => 'PO-'.str_pad(self::$poNumber++, 6, '0', STR_PAD_LEFT),
            'vendor_id' => Customer::factory()->state(['is_vendor' => true, 'type' => 'company']),
            'order_date' => $orderDate,
            'expected_delivery_date' => (clone $orderDate)->modify('+14 days'),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'status' => PurchaseOrderStatus::Draft,
            'notes' => fake()->boolean(30) ? fake()->paragraph() : null,
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Draft,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Sent,
            'sent_at' => fake()->dateTimeBetween($attributes['order_date'], 'now'),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Confirmed,
            'sent_at' => fake()->dateTimeBetween($attributes['order_date'], 'now'),
            'confirmed_at' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Received,
            'sent_at' => fake()->dateTimeBetween($attributes['order_date'], 'now'),
            'confirmed_at' => now(),
            'received_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => fake()->sentence(),
        ]);
    }
}
