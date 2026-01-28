<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        $banks = [
            'Commonwealth Bank',
            'Westpac',
            'ANZ',
            'NAB',
            'Bendigo Bank',
            'Bank of Queensland',
            'Macquarie Bank',
            'ING',
        ];

        return [
            'account_id' => Account::where('code', '1010')->first()?->id,
            'bank_name' => fake()->randomElement($banks),
            'account_name' => fake()->randomElement(['Operating Account', 'Business Account', 'Trading Account', 'Primary Account']),
            'account_number' => fake()->numerify('########'),
            'bsb' => fake()->numerify('###-###'),
            'currency' => 'AUD',
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
