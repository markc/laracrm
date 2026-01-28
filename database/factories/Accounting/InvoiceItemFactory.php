<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 20);
        $unitPrice = fake()->randomFloat(2, 50, 2000);
        $discountPercent = fake()->boolean(20) ? fake()->randomFloat(2, 5, 15) : 0;
        $subtotal = $quantity * $unitPrice;
        $discountAmount = round($subtotal * ($discountPercent / 100), 2);
        $taxRate = 10.00;
        $taxableAmount = $subtotal - $discountAmount;
        $taxAmount = round($taxableAmount * ($taxRate / 100), 2);
        $totalAmount = $taxableAmount + $taxAmount;

        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => null,
            'description' => fake()->randomElement([
                'Web Development Services',
                'Monthly Hosting Fee',
                'Domain Registration',
                'SSL Certificate Annual',
                'IT Support Hours',
                'Graphic Design Services',
                'SEO Optimization Package',
                'Email Hosting Annual',
                'Cloud Backup Service',
                'Security Audit',
            ]),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'sort_order' => 0,
        ];
    }

    public function withProduct(): static
    {
        return $this->state(function (array $attributes) {
            $product = Product::inRandomOrder()->first();

            if (! $product) {
                return $attributes;
            }

            $quantity = $attributes['quantity'];
            $unitPrice = $product->unit_price;
            $discountPercent = $attributes['discount_percent'];
            $subtotal = $quantity * $unitPrice;
            $discountAmount = round($subtotal * ($discountPercent / 100), 2);
            $taxRate = $product->tax_rate;
            $taxableAmount = $subtotal - $discountAmount;
            $taxAmount = round($taxableAmount * ($taxRate / 100), 2);
            $totalAmount = $taxableAmount + $taxAmount;

            return [
                'product_id' => $product->id,
                'description' => $product->name,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
            ];
        });
    }
}
