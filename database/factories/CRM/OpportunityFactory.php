<?php

namespace Database\Factories\CRM;

use App\Enums\OpportunityStage;
use App\Models\CRM\Customer;
use App\Models\CRM\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CRM\Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        $stage = fake()->randomElement(OpportunityStage::cases());

        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->randomElement([
                'Website Redesign Project',
                'Annual Hosting Contract',
                'Software Development',
                'IT Support Agreement',
                'Cloud Migration',
                'Security Audit',
                'Mobile App Development',
                'E-commerce Platform',
                'CRM Implementation',
                'Data Analytics Solution',
            ]).' - '.fake()->company(),
            'value' => fake()->randomFloat(2, 500, 150000),
            'probability' => $stage->probability(),
            'stage' => $stage,
            'expected_close_date' => fake()->dateTimeBetween('-1 month', '+6 months'),
            'lost_reason' => $stage === OpportunityStage::Lost
                ? fake()->randomElement(['Price too high', 'Chose competitor', 'Project cancelled', 'Budget constraints', 'Timing not right'])
                : null,
            'won_at' => $stage === OpportunityStage::Won ? fake()->dateTimeBetween('-3 months', 'now') : null,
            'lost_at' => $stage === OpportunityStage::Lost ? fake()->dateTimeBetween('-3 months', 'now') : null,
            'notes' => fake()->boolean(40) ? fake()->paragraph() : null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => fake()->randomElement([
                OpportunityStage::Lead,
                OpportunityStage::Qualified,
                OpportunityStage::Proposal,
                OpportunityStage::Negotiation,
            ]),
            'won_at' => null,
            'lost_at' => null,
            'lost_reason' => null,
        ]);
    }

    public function won(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => OpportunityStage::Won,
            'probability' => 100,
            'won_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'lost_at' => null,
            'lost_reason' => null,
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => OpportunityStage::Lost,
            'probability' => 0,
            'lost_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'lost_reason' => fake()->randomElement(['Price too high', 'Chose competitor', 'Project cancelled', 'Budget constraints']),
            'won_at' => null,
        ]);
    }

    public function withAssignedUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => User::inRandomOrder()->first()?->id ?? User::factory(),
        ]);
    }
}
