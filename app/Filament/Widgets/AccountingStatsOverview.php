<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        // This month's invoiced amount
        $invoicedThisMonth = Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->whereDate('invoice_date', '>=', $thisMonth)
            ->sum('total_amount');

        $invoicedLastMonth = Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->whereBetween('invoice_date', [$lastMonth, $lastMonthEnd])
            ->sum('total_amount');

        // This month's collected amount
        $collectedThisMonth = Payment::query()
            ->whereDate('payment_date', '>=', $thisMonth)
            ->sum('amount');

        $collectedLastMonth = Payment::query()
            ->whereBetween('payment_date', [$lastMonth, $lastMonthEnd])
            ->sum('amount');

        // Outstanding balance
        $outstandingBalance = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue])
            ->sum('balance_due');

        // Overdue invoices
        $overdueCount = Invoice::query()
            ->where('status', InvoiceStatus::Overdue)
            ->count();

        $overdueAmount = Invoice::query()
            ->where('status', InvoiceStatus::Overdue)
            ->sum('balance_due');

        return [
            Stat::make('Invoiced This Month', '$'.number_format($invoicedThisMonth, 2))
                ->description($this->getChangeDescription($invoicedThisMonth, $invoicedLastMonth))
                ->descriptionIcon($this->getChangeIcon($invoicedThisMonth, $invoicedLastMonth))
                ->color($this->getChangeColor($invoicedThisMonth, $invoicedLastMonth))
                ->chart($this->getInvoiceChartData()),

            Stat::make('Collected This Month', '$'.number_format($collectedThisMonth, 2))
                ->description($this->getChangeDescription($collectedThisMonth, $collectedLastMonth))
                ->descriptionIcon($this->getChangeIcon($collectedThisMonth, $collectedLastMonth))
                ->color($this->getChangeColor($collectedThisMonth, $collectedLastMonth))
                ->chart($this->getPaymentChartData()),

            Stat::make('Outstanding Balance', '$'.number_format($outstandingBalance, 2))
                ->description($overdueCount.' overdue ($'.number_format($overdueAmount, 2).')')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueCount > 0 ? 'danger' : 'success'),
        ];
    }

    protected function getChangeDescription(float $current, float $previous): string
    {
        if ($previous == 0) {
            return $current > 0 ? 'New this month' : 'No change';
        }

        $change = (($current - $previous) / $previous) * 100;

        return number_format(abs($change), 1).'% '.($change >= 0 ? 'increase' : 'decrease');
    }

    protected function getChangeIcon(float $current, float $previous): string
    {
        return $current >= $previous ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
    }

    protected function getChangeColor(float $current, float $previous): string
    {
        return $current >= $previous ? 'success' : 'danger';
    }

    protected function getInvoiceChartData(): array
    {
        return Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->whereDate('invoice_date', '>=', now()->subDays(7))
            ->selectRaw('DATE(invoice_date) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total')
            ->toArray();
    }

    protected function getPaymentChartData(): array
    {
        return Payment::query()
            ->whereDate('payment_date', '>=', now()->subDays(7))
            ->selectRaw('DATE(payment_date) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total')
            ->toArray();
    }
}
