<?php

namespace Database\Factories\Accounting;

use App\Enums\QuoteStatus;
use App\Models\Accounting\Quote;
use App\Models\CRM\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    protected static int $quoteNumber = 1000;

    public function definition(): array
    {
        $status = fake()->randomElement(QuoteStatus::cases());
        $quoteDate = fake()->dateTimeBetween('-3 months', 'now');
        $validUntil = (clone $quoteDate)->modify('+30 days');

        $subtotal = fake()->randomFloat(2, 500, 50000);
        $taxAmount = round($subtotal * 0.10, 2);
        $discountAmount = fake()->boolean(25) ? round($subtotal * fake()->randomFloat(2, 0.05, 0.20), 2) : 0;
        $totalAmount = $subtotal + $taxAmount - $discountAmount;

        return [
            'quote_number' => 'QUO-'.str_pad(self::$quoteNumber++, 6, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'opportunity_id' => null,
            'quote_date' => $quoteDate,
            'valid_until' => $validUntil,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'status' => $status,
            'notes' => fake()->boolean(40) ? fake()->paragraph() : null,
            'terms' => fake()->boolean(50) ? 'Quote valid for 30 days. Prices subject to change.' : null,
            'sent_at' => in_array($status, [QuoteStatus::Sent, QuoteStatus::Approved, QuoteStatus::Rejected, QuoteStatus::Converted])
                ? fake()->dateTimeBetween($quoteDate, 'now') : null,
            'approved_at' => in_array($status, [QuoteStatus::Approved, QuoteStatus::Converted])
                ? fake()->dateTimeBetween($quoteDate, 'now') : null,
            'invoice_id' => null,
            'created_by' => User::inRandomOrder()->first()?->id,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Draft,
            'sent_at' => null,
            'approved_at' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => QuoteStatus::Sent,
                'sent_at' => fake()->dateTimeBetween($attributes['quote_date'], 'now'),
                'approved_at' => null,
            ];
        });
    }

    public function approved(): static
    {
        return $this->state(function (array $attributes) {
            $sentAt = fake()->dateTimeBetween($attributes['quote_date'], 'now');

            return [
                'status' => QuoteStatus::Approved,
                'sent_at' => $sentAt,
                'approved_at' => fake()->dateTimeBetween($sentAt, 'now'),
            ];
        });
    }

    public function rejected(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => QuoteStatus::Rejected,
                'sent_at' => fake()->dateTimeBetween($attributes['quote_date'], 'now'),
                'approved_at' => null,
            ];
        });
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Expired,
            'quote_date' => fake()->dateTimeBetween('-3 months', '-2 months'),
            'valid_until' => fake()->dateTimeBetween('-2 months', '-1 month'),
            'sent_at' => fake()->dateTimeBetween('-3 months', '-2 months'),
            'approved_at' => null,
        ]);
    }
}
