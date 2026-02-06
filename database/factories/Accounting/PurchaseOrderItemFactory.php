<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\PurchaseOrder;
use App\Models\Accounting\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 100);
        $unitPrice = fake()->randomFloat(2, 10, 500);
        $taxRate = 10.00;
        $subtotal = $quantity * $unitPrice;
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => null,
            'description' => fake()->randomElement([
                'Industrial Cleaning Solution',
                'Degreaser Concentrate',
                'Floor Polish',
                'Sanitiser Liquid',
                'Glass Cleaner',
                'Multi-Surface Wipes',
                'Protective Gloves',
                'Spray Bottles',
            ]),
            'quantity' => $quantity,
            'received_quantity' => 0,
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'sort_order' => 0,
        ];
    }
}
