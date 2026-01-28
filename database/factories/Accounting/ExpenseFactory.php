<?php

namespace Database\Factories\Accounting;

use App\Enums\PaymentMethod;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\Expense;
use App\Models\CRM\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    protected static int $expenseNumber = 1000;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 20, 5000);
        $taxAmount = round($amount * 0.10, 2);
        $totalAmount = $amount + $taxAmount;

        $expenseAccounts = Account::whereIn('code', ['5100', '5200', '6000', '6100', '6300', '6500', '6600', '6800', '6900', '7000', '7100'])
            ->pluck('id')
            ->toArray();

        $descriptions = [
            'Server hosting fees',
            'Domain registration',
            'Software subscription',
            'Office supplies',
            'Internet bill',
            'Phone bill',
            'Professional services',
            'Bank fees',
            'Insurance premium',
            'Marketing expenses',
            'Travel expenses',
            'Equipment purchase',
            'Utilities bill',
            'Advertising costs',
        ];

        return [
            'expense_number' => 'EXP-'.str_pad(self::$expenseNumber++, 6, '0', STR_PAD_LEFT),
            'vendor_id' => Customer::inRandomOrder()->first()?->id,
            'account_id' => ! empty($expenseAccounts) ? fake()->randomElement($expenseAccounts) : null,
            'bank_account_id' => BankAccount::inRandomOrder()->first()?->id,
            'expense_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'reference_number' => fake()->boolean(60) ? fake()->bothify('INV-####') : null,
            'description' => fake()->randomElement($descriptions),
            'is_billable' => fake()->boolean(20),
            'customer_id' => fake()->boolean(20) ? Customer::inRandomOrder()->first()?->id : null,
            'status' => fake()->randomElement(['draft', 'approved', 'paid']),
            'created_by' => User::inRandomOrder()->first()?->id,
        ];
    }

    public function billable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_billable' => true,
            'customer_id' => Customer::inRandomOrder()->first()?->id ?? Customer::factory(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
        ]);
    }
}
