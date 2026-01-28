<?php

namespace Database\Factories\Accounting;

use App\Enums\ProductType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $type = fake()->randomElement(ProductType::cases());
        $unitPrice = fake()->randomFloat(2, 50, 5000);
        $costPrice = $unitPrice * fake()->randomFloat(2, 0.3, 0.7);

        $products = $type === ProductType::Service ? [
            'Web Development Hourly',
            'Graphic Design Services',
            'SEO Optimization',
            'IT Support Hourly',
            'Consulting Services',
            'Project Management',
            'Content Writing',
            'Social Media Management',
            'Server Maintenance',
            'Database Administration',
        ] : [
            'Web Hosting - Basic',
            'Web Hosting - Pro',
            'Web Hosting - Enterprise',
            'Domain Registration',
            'SSL Certificate',
            'Email Hosting',
            'Cloud Storage 100GB',
            'Cloud Storage 1TB',
            'Software License',
            'Hardware Component',
        ];

        return [
            'sku' => strtoupper(fake()->unique()->bothify('???-####')),
            'name' => fake()->randomElement($products),
            'description' => fake()->boolean(70) ? fake()->paragraph() : null,
            'unit_price' => $unitPrice,
            'cost_price' => $costPrice,
            'tax_rate' => 10.00,
            'income_account_id' => Account::where('code', '4000')->first()?->id,
            'expense_account_id' => Account::where('code', '5000')->first()?->id,
            'type' => $type,
            'is_active' => fake()->boolean(90),
        ];
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Service,
        ]);
    }

    public function product(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Product,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
