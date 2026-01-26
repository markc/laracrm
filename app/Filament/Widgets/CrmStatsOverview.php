<?php

namespace App\Filament\Widgets;

use App\Enums\OpportunityStage;
use App\Models\CRM\Activity;
use App\Models\CRM\Customer;
use App\Models\CRM\Opportunity;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CrmStatsOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // Active customers
        $activeCustomers = Customer::query()
            ->where('status', 'active')
            ->count();

        $newCustomersThisMonth = Customer::query()
            ->whereDate('created_at', '>=', now()->startOfMonth())
            ->count();

        // Open opportunities
        $openOpportunities = Opportunity::open()->count();
        $pipelineValue = Opportunity::open()->sum('value');
        $weightedPipeline = Opportunity::open()
            ->get()
            ->sum(fn ($opp) => $opp->value * ($opp->probability / 100));

        // Won this month
        $wonThisMonth = Opportunity::query()
            ->where('stage', OpportunityStage::Won)
            ->whereDate('won_at', '>=', now()->startOfMonth())
            ->sum('value');

        // Pending activities
        $pendingActivities = Activity::pending()->count();
        $overdueActivities = Activity::overdue()->count();

        return [
            Stat::make('Active Customers', number_format($activeCustomers))
                ->description($newCustomersThisMonth.' new this month')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success'),

            Stat::make('Pipeline Value', '$'.number_format($pipelineValue, 2))
                ->description($openOpportunities.' open opportunities')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('primary'),

            Stat::make('Won This Month', '$'.number_format($wonThisMonth, 2))
                ->description('Weighted pipeline: $'.number_format($weightedPipeline, 2))
                ->descriptionIcon('heroicon-m-trophy')
                ->color('success'),

            Stat::make('Pending Activities', number_format($pendingActivities))
                ->description($overdueActivities > 0 ? $overdueActivities.' overdue' : 'All on track')
                ->descriptionIcon($overdueActivities > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdueActivities > 0 ? 'danger' : 'success'),
        ];
    }
}
