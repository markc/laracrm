<?php

namespace App\Filament\Pages\Reports;

use App\Services\Accounting\ReportingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

class RevenueSummary extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Revenue Summary';

    protected string $view = 'filament.pages.reports.revenue-summary';

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
        $this->reportData = app(ReportingService::class)->getRevenueSummary(
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
