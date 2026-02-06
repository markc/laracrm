<?php

namespace App\Filament\Pages\Reports;

use App\Services\Accounting\ReportingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

class ArAging extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'AR Aging';

    protected static ?string $navigationLabel = 'AR Aging';

    protected string $view = 'filament.pages.reports.ar-aging';

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
        $this->reportData = app(ReportingService::class)->getAccountsReceivableAging(
            Carbon::parse($this->asOfDate),
        );
    }

    public function updatedAsOfDate(): void
    {
        $this->loadReport();
    }
}
