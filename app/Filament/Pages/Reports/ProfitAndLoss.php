<?php

namespace App\Filament\Pages\Reports;

use App\Services\Accounting\ReportingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

class ProfitAndLoss extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Profit & Loss';

    protected static ?string $navigationLabel = 'Profit & Loss';

    protected string $view = 'filament.pages.reports.profit-and-loss';

    #[Url]
    public string $startDate = '';

    #[Url]
    public string $endDate = '';

    public array $reportData = [];

    public function mount(): void
    {
        $this->startDate = $this->startDate ?: now()->startOfMonth()->toDateString();
        $this->endDate = $this->endDate ?: now()->toDateString();
        $this->loadReport();
    }

    public function loadReport(): void
    {
        $this->reportData = app(ReportingService::class)->getProfitAndLoss(
            Carbon::parse($this->startDate),
            Carbon::parse($this->endDate),
        );
    }

    public function updatedStartDate(): void
    {
        $this->loadReport();
    }

    public function updatedEndDate(): void
    {
        $this->loadReport();
    }
}
