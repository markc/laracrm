<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->randomFloat(2, 100, 5000),
        ];
    }
}
