<?php

namespace Database\Factories\CRM;

use App\Enums\ActivityType;
use App\Models\CRM\Activity;
use App\Models\CRM\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CRM\Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        $type = fake()->randomElement(ActivityType::cases());
        $activityDate = fake()->dateTimeBetween('-3 months', '+1 month');
        $hasDueDate = in_array($type, [ActivityType::Task, ActivityType::Meeting, ActivityType::Call]);
        $isCompleted = fake()->boolean(60);

        return [
            'customer_id' => Customer::factory(),
            'contact_id' => null,
            'opportunity_id' => null,
            'type' => $type,
            'subject' => $this->getSubjectForType($type),
            'description' => fake()->boolean(70) ? fake()->paragraph() : null,
            'activity_date' => $activityDate,
            'due_date' => $hasDueDate ? fake()->dateTimeBetween($activityDate, '+2 weeks') : null,
            'completed_at' => $isCompleted ? fake()->dateTimeBetween($activityDate, 'now') : null,
            'created_by' => User::inRandomOrder()->first()?->id,
        ];
    }

    private function getSubjectForType(ActivityType $type): string
    {
        return match ($type) {
            ActivityType::Call => fake()->randomElement([
                'Follow-up call',
                'Initial contact call',
                'Sales call',
                'Support call',
                'Quarterly check-in',
                'Project discussion',
            ]),
            ActivityType::Email => fake()->randomElement([
                'Sent proposal',
                'Follow-up email',
                'Quote request',
                'Invoice reminder',
                'Project update',
                'Welcome email',
            ]),
            ActivityType::Meeting => fake()->randomElement([
                'Sales presentation',
                'Project kickoff',
                'Quarterly review',
                'Demo meeting',
                'Contract negotiation',
                'Requirements gathering',
            ]),
            ActivityType::Task => fake()->randomElement([
                'Prepare proposal',
                'Update CRM records',
                'Send contract',
                'Review project scope',
                'Schedule follow-up',
                'Complete assessment',
            ]),
            ActivityType::Note => fake()->randomElement([
                'Customer feedback',
                'Meeting notes',
                'Important update',
                'Internal note',
                'Contact preference',
                'Special requirements',
            ]),
        };
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $activityDate = $attributes['activity_date'] ?? now();

            return [
                'completed_at' => fake()->dateTimeBetween($activityDate, 'now'),
            ];
        });
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => fake()->dateTimeBetween('-2 weeks', '-1 day'),
            'completed_at' => null,
        ]);
    }

    public function withAssignedUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => User::inRandomOrder()->first()?->id ?? User::factory(),
        ]);
    }
}
