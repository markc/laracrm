<?php

namespace App\Filament\Pages\Reports;

use App\Services\Accounting\ReportingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

class TrialBalance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Trial Balance';

    protected string $view = 'filament.pages.reports.trial-balance';

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
        $this->reportData = app(ReportingService::class)->getTrialBalance(
            Carbon::parse($this->asOfDate),
        );
    }

    public function updatedAsOfDate(): void
    {
        $this->loadReport();
    }
}
