<?php

namespace App\Filament\Widgets;

use App\Enums\OpportunityStage;
use App\Models\CRM\Opportunity;
use Filament\Widgets\ChartWidget;

class OpportunityPipelineChart extends ChartWidget
{
    protected ?string $heading = 'Opportunity Pipeline';

    protected static ?int $sort = 4;

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $stages = collect(OpportunityStage::cases())
            ->filter(fn ($stage) => ! in_array($stage, [OpportunityStage::Won, OpportunityStage::Lost]));

        $data = $stages->map(function ($stage) {
            return Opportunity::where('stage', $stage)->sum('value');
        })->values()->toArray();

        $labels = $stages->map(fn ($stage) => $stage->getLabel())->values()->toArray();

        $colors = $stages->map(function ($stage) {
            return match ($stage->getColor()) {
                'gray' => '#6b7280',
                'info' => '#3b82f6',
                'warning' => '#f59e0b',
                'primary' => '#8b5cf6',
                'success' => '#10b981',
                'danger' => '#ef4444',
                default => '#6b7280',
            };
        })->values()->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Pipeline Value',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => '(value) => "$" + value.toLocaleString()',
                    ],
                ],
            ],
        ];
    }
}
