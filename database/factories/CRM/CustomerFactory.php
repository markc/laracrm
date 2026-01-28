<?php

namespace Database\Factories\CRM;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\CRM\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CRM\Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $type = fake()->randomElement(CustomerType::cases());
        $isCompany = $type === CustomerType::Company;

        return [
            'customer_number' => 'CUS-'.fake()->unique()->numerify('######'),
            'company_name' => $isCompany ? fake()->company() : null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'tax_id' => $isCompany ? fake()->numerify('## ### ### ###') : null,
            'type' => $type,
            'billing_address' => [
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->randomElement(['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'NT', 'ACT']),
                'postcode' => fake()->postcode(),
                'country' => 'Australia',
            ],
            'shipping_address' => fake()->boolean(70) ? [
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->randomElement(['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'NT', 'ACT']),
                'postcode' => fake()->postcode(),
                'country' => 'Australia',
            ] : null,
            'payment_terms' => fake()->randomElement([7, 14, 30, 45, 60]),
            'credit_limit' => fake()->randomElement([1000, 5000, 10000, 25000, 50000, null]),
            'currency' => 'AUD',
            'status' => fake()->randomElement(CustomerStatus::cases()),
            'notes' => fake()->boolean(30) ? fake()->paragraph() : null,
        ];
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CustomerType::Company,
            'company_name' => fake()->company(),
            'tax_id' => fake()->numerify('## ### ### ###'),
        ]);
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CustomerType::Individual,
            'company_name' => null,
            'tax_id' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::Active,
        ]);
    }

    public function withAssignedUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => User::inRandomOrder()->first()?->id ?? User::factory(),
        ]);
    }
}
