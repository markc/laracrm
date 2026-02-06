<?php

namespace App\Filament\Pages\Reports;

use App\Models\CRM\Customer;
use App\Services\Accounting\ReportingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

class CustomerStatement extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Customer Statement';

    protected string $view = 'filament.pages.reports.customer-statement';

    #[Url]
    public string $customerId = '';

    #[Url]
    public string $startDate = '';

    #[Url]
    public string $endDate = '';

    public array $reportData = [];

    /** @return array<int, array{id: int, name: string}> */
    public function getCustomerOptions(): array
    {
        return Customer::active()
            ->orderBy('company_name')
            ->get()
            ->mapWithKeys(fn ($c) => [$c->id => $c->display_name])
            ->toArray();
    }

    public function mount(): void
    {
        $this->startDate = $this->startDate ?: now()->startOfMonth()->toDateString();
        $this->endDate = $this->endDate ?: now()->toDateString();

        if ($this->customerId) {
            $this->loadReport();
        }
    }

    public function loadReport(): void
    {
        if (! $this->customerId) {
            $this->reportData = [];

            return;
        }

        $customer = Customer::find($this->customerId);
        if (! $customer) {
            $this->reportData = [];

            return;
        }

        $this->reportData = app(ReportingService::class)->getCustomerStatement(
            $customer,
            Carbon::parse($this->startDate),
            Carbon::parse($this->endDate),
        );
    }

    public function updatedCustomerId(): void
    {
        $this->loadReport();
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
