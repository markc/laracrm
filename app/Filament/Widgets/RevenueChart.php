<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Revenue Overview';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
            '365' => 'This year',
        ];
    }

    protected function getData(): array
    {
        $days = (int) $this->filter;
        $startDate = now()->subDays($days);

        $invoices = Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->whereDate('invoice_date', '>=', $startDate)
            ->selectRaw('DATE(invoice_date) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $payments = Payment::query()
            ->whereDate('payment_date', '>=', $startDate)
            ->selectRaw('DATE(payment_date) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        // Generate all dates in range
        $labels = [];
        $invoiceData = [];
        $paymentData = [];

        $currentDate = $startDate->copy();
        while ($currentDate <= now()) {
            $dateKey = $currentDate->toDateString();
            $labels[] = $currentDate->format('M j');
            $invoiceData[] = $invoices[$dateKey] ?? 0;
            $paymentData[] = $payments[$dateKey] ?? 0;
            $currentDate->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Invoiced',
                    'data' => $invoiceData,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Collected',
                    'data' => $paymentData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
