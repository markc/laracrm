<?php

namespace Database\Factories\Accounting;

use App\Enums\PaymentMethod;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\Payment;
use App\Models\CRM\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    protected static int $paymentNumber = 1000;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 15000);
        $allocatedAmount = fake()->boolean(70) ? $amount : fake()->randomFloat(2, 0, $amount);
        $unallocatedAmount = $amount - $allocatedAmount;

        return [
            'payment_number' => 'PAY-'.str_pad(self::$paymentNumber++, 6, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'payment_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'amount' => $amount,
            'allocated_amount' => $allocatedAmount,
            'unallocated_amount' => $unallocatedAmount,
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'reference_number' => fake()->boolean(70) ? fake()->bothify('REF-####-????') : null,
            'bank_account_id' => BankAccount::inRandomOrder()->first()?->id,
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
            'created_by' => User::inRandomOrder()->first()?->id,
        ];
    }

    public function fullyAllocated(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'allocated_amount' => $attributes['amount'],
                'unallocated_amount' => 0,
            ];
        });
    }

    public function unallocated(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocated_amount' => 0,
            'unallocated_amount' => $attributes['amount'],
        ]);
    }

    public function partiallyAllocated(): static
    {
        return $this->state(function (array $attributes) {
            $allocatedAmount = round($attributes['amount'] * fake()->randomFloat(2, 0.2, 0.8), 2);

            return [
                'allocated_amount' => $allocatedAmount,
                'unallocated_amount' => $attributes['amount'] - $allocatedAmount,
            ];
        });
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::BankTransfer,
        ]);
    }

    public function card(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Card,
        ]);
    }
}
