<?php

namespace Database\Factories\Accounting;

use App\Enums\InvoiceStatus;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    protected static int $invoiceNumber = 1000;

    public function definition(): array
    {
        $status = fake()->randomElement(InvoiceStatus::cases());
        $invoiceDate = fake()->dateTimeBetween('-6 months', 'now');
        $paymentTerms = fake()->randomElement([7, 14, 30, 45, 60]);
        $dueDate = (clone $invoiceDate)->modify("+{$paymentTerms} days");

        $subtotal = fake()->randomFloat(2, 100, 10000);
        $taxAmount = round($subtotal * 0.10, 2);
        $discountAmount = fake()->boolean(20) ? round($subtotal * fake()->randomFloat(2, 0.05, 0.15), 2) : 0;
        $totalAmount = $subtotal + $taxAmount - $discountAmount;

        $paidAmount = match ($status) {
            InvoiceStatus::Paid => $totalAmount,
            InvoiceStatus::Partial => round($totalAmount * fake()->randomFloat(2, 0.2, 0.8), 2),
            default => 0,
        };
        $balanceDue = $totalAmount - $paidAmount;

        return [
            'invoice_number' => 'INV-'.str_pad(self::$invoiceNumber++, 6, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'quote_id' => null,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'balance_due' => $balanceDue,
            'status' => $status,
            'currency' => 'AUD',
            'exchange_rate' => 1.000000,
            'notes' => fake()->boolean(30) ? fake()->sentence() : null,
            'terms' => fake()->boolean(50) ? 'Payment due within '.$paymentTerms.' days. Late payments may incur interest.' : null,
            'sent_at' => in_array($status, [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Paid, InvoiceStatus::Overdue])
                ? fake()->dateTimeBetween($invoiceDate, 'now') : null,
            'paid_at' => $status === InvoiceStatus::Paid ? fake()->dateTimeBetween($invoiceDate, 'now') : null,
            'voided_at' => $status === InvoiceStatus::Void ? fake()->dateTimeBetween($invoiceDate, 'now') : null,
            'void_reason' => $status === InvoiceStatus::Void ? fake()->randomElement(['Duplicate invoice', 'Customer cancelled', 'Issued in error']) : null,
            'created_by' => User::inRandomOrder()->first()?->id,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Draft,
            'sent_at' => null,
            'paid_at' => null,
            'paid_amount' => 0,
            'balance_due' => $attributes['total_amount'],
        ]);
    }

    public function sent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => InvoiceStatus::Sent,
                'sent_at' => fake()->dateTimeBetween($attributes['invoice_date'], 'now'),
                'paid_at' => null,
                'paid_amount' => 0,
                'balance_due' => $attributes['total_amount'],
            ];
        });
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => InvoiceStatus::Paid,
                'sent_at' => fake()->dateTimeBetween($attributes['invoice_date'], 'now'),
                'paid_at' => fake()->dateTimeBetween($attributes['invoice_date'], 'now'),
                'paid_amount' => $attributes['total_amount'],
                'balance_due' => 0,
            ];
        });
    }

    public function partial(): static
    {
        return $this->state(function (array $attributes) {
            $paidAmount = round($attributes['total_amount'] * fake()->randomFloat(2, 0.2, 0.8), 2);

            return [
                'status' => InvoiceStatus::Partial,
                'sent_at' => fake()->dateTimeBetween($attributes['invoice_date'], 'now'),
                'paid_amount' => $paidAmount,
                'balance_due' => $attributes['total_amount'] - $paidAmount,
            ];
        });
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Overdue,
            'invoice_date' => fake()->dateTimeBetween('-3 months', '-2 months'),
            'due_date' => fake()->dateTimeBetween('-2 months', '-1 week'),
            'sent_at' => fake()->dateTimeBetween('-3 months', '-2 months'),
            'paid_at' => null,
            'paid_amount' => 0,
            'balance_due' => $attributes['total_amount'],
        ]);
    }

    public function void(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => InvoiceStatus::Void,
                'voided_at' => fake()->dateTimeBetween($attributes['invoice_date'], 'now'),
                'void_reason' => fake()->randomElement(['Duplicate invoice', 'Customer cancelled', 'Issued in error']),
                'paid_amount' => 0,
                'balance_due' => 0,
            ];
        });
    }
}
