<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Product;
use App\Models\Accounting\Quote;
use App\Models\Accounting\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    protected $model = QuoteItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 50);
        $unitPrice = fake()->randomFloat(2, 100, 5000);
        $discountPercent = fake()->boolean(30) ? fake()->randomFloat(2, 5, 20) : 0;
        $subtotal = $quantity * $unitPrice;
        $discountAmount = round($subtotal * ($discountPercent / 100), 2);
        $taxRate = 10.00;
        $taxableAmount = $subtotal - $discountAmount;
        $taxAmount = round($taxableAmount * ($taxRate / 100), 2);
        $totalAmount = $taxableAmount + $taxAmount;

        return [
            'quote_id' => Quote::factory(),
            'product_id' => null,
            'description' => fake()->randomElement([
                'Web Application Development',
                'Mobile App Development',
                'E-commerce Platform Setup',
                'Annual Hosting Package',
                'Server Migration Service',
                'Database Optimization',
                'Security Implementation',
                'API Integration',
                'Custom Software Development',
                'Technical Consultation',
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
