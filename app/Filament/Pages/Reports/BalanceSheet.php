<?php

namespace App\Filament\Pages\Reports;

use App\Services\Accounting\ReportingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

class BalanceSheet extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Balance Sheet';

    protected string $view = 'filament.pages.reports.balance-sheet';

    #[Url]
    public string $asOfDate = '';

    public array $reportData = [];

    public function mount(): void
    {
        $this->asOfDate = $this->asOfDate ?: now()->toDateString();
        $this->loadReport();
    }

    public function loadReport(): void
    {
        $this->reportData = app(ReportingService::class)->getBalanceSheet(
            Carbon::parse($this->asOfDate),
        );
    }

    public function updatedAsOfDate(): void
    {
        $this->loadReport();
    }
}
