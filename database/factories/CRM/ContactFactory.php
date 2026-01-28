<?php

namespace Database\Factories\CRM;

use App\Models\CRM\Contact;
use App\Models\CRM\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CRM\Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile' => fake()->boolean(70) ? fake()->phoneNumber() : null,
            'position' => fake()->randomElement([
                'CEO', 'CFO', 'CTO', 'Director', 'Manager', 'Owner',
                'Accounts Manager', 'Sales Manager', 'IT Manager',
                'Office Manager', 'Project Manager', 'Operations Manager',
            ]),
            'is_primary' => false,
            'notes' => fake()->boolean(20) ? fake()->sentence() : null,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
