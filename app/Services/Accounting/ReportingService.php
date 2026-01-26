<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Enums\InvoiceStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Payment;
use App\Models\CRM\Customer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    public function getProfitAndLoss(Carbon $startDate, Carbon $endDate): array
    {
        $revenue = $this->getAccountBalances(AccountType::Revenue, $startDate, $endDate);
        $expenses = $this->getAccountBalances(AccountType::Expense, $startDate, $endDate);

        $totalRevenue = $revenue->sum('balance');
        $totalExpenses = $expenses->sum('balance');
        $netIncome = $totalRevenue - $totalExpenses;

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'revenue' => [
                'accounts' => $revenue->toArray(),
                'total' => $totalRevenue,
            ],
            'expenses' => [
                'accounts' => $expenses->toArray(),
                'total' => $totalExpenses,
            ],
            'net_income' => $netIncome,
        ];
    }

    public function getBalanceSheet(Carbon $asOfDate): array
    {
        $assets = $this->getAccountBalancesAsOf(AccountType::Asset, $asOfDate);
        $liabilities = $this->getAccountBalancesAsOf(AccountType::Liability, $asOfDate);
        $equity = $this->getAccountBalancesAsOf(AccountType::Equity, $asOfDate);

        // Add retained earnings (accumulated P&L)
        $retainedEarnings = $this->calculateRetainedEarnings($asOfDate);

        $totalAssets = $assets->sum('balance');
        $totalLiabilities = $liabilities->sum('balance');
        $totalEquity = $equity->sum('balance') + $retainedEarnings;

        return [
            'as_of_date' => $asOfDate->toDateString(),
            'assets' => [
                'accounts' => $assets->toArray(),
                'total' => $totalAssets,
            ],
            'liabilities' => [
                'accounts' => $liabilities->toArray(),
                'total' => $totalLiabilities,
            ],
            'equity' => [
                'accounts' => $equity->toArray(),
                'retained_earnings' => $retainedEarnings,
                'total' => $totalEquity,
            ],
            'balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01,
        ];
    }

    public function getTrialBalance(Carbon $asOfDate): array
    {
        $accounts = Account::query()
            ->with(['journalEntryLines' => function ($query) use ($asOfDate) {
                $query->whereHas('journalEntry', function ($q) use ($asOfDate) {
                    $q->where('is_posted', true)
                        ->whereDate('entry_date', '<=', $asOfDate);
                });
            }])
            ->orderBy('code')
            ->get()
            ->map(function ($account) {
                $debits = $account->journalEntryLines->sum('debit_amount');
                $credits = $account->journalEntryLines->sum('credit_amount');

                return [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type->value,
                    'debit_balance' => $debits > $credits ? $debits - $credits : 0,
                    'credit_balance' => $credits > $debits ? $credits - $debits : 0,
                ];
            })
            ->filter(fn ($account) => $account['debit_balance'] > 0 || $account['credit_balance'] > 0);

        return [
            'as_of_date' => $asOfDate->toDateString(),
            'accounts' => $accounts->values()->toArray(),
            'total_debits' => $accounts->sum('debit_balance'),
            'total_credits' => $accounts->sum('credit_balance'),
            'balanced' => abs($accounts->sum('debit_balance') - $accounts->sum('credit_balance')) < 0.01,
        ];
    }

    public function getAccountsReceivableAging(?Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now();

        $invoices = Invoice::query()
            ->with('customer')
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue])
            ->where('balance_due', '>', 0)
            ->whereDate('invoice_date', '<=', $asOfDate)
            ->get();

        $aging = [
            'current' => ['invoices' => [], 'total' => 0],
            '1_30' => ['invoices' => [], 'total' => 0],
            '31_60' => ['invoices' => [], 'total' => 0],
            '61_90' => ['invoices' => [], 'total' => 0],
            'over_90' => ['invoices' => [], 'total' => 0],
        ];

        foreach ($invoices as $invoice) {
            $daysOverdue = $invoice->due_date->diffInDays($asOfDate, false);
            $bucket = match (true) {
                $daysOverdue <= 0 => 'current',
                $daysOverdue <= 30 => '1_30',
                $daysOverdue <= 60 => '31_60',
                $daysOverdue <= 90 => '61_90',
                default => 'over_90',
            };

            $aging[$bucket]['invoices'][] = [
                'invoice_number' => $invoice->invoice_number,
                'customer' => $invoice->customer->display_name,
                'invoice_date' => $invoice->invoice_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'days_overdue' => max(0, $daysOverdue),
                'balance_due' => $invoice->balance_due,
            ];
            $aging[$bucket]['total'] += $invoice->balance_due;
        }

        return [
            'as_of_date' => $asOfDate->toDateString(),
            'aging' => $aging,
            'grand_total' => array_sum(array_column($aging, 'total')),
        ];
    }

    public function getCustomerStatement(Customer $customer, Carbon $startDate, Carbon $endDate): array
    {
        $openingBalance = Invoice::where('customer_id', $customer->id)
            ->whereDate('invoice_date', '<', $startDate)
            ->sum('balance_due');

        $invoices = Invoice::where('customer_id', $customer->id)
            ->whereDate('invoice_date', '>=', $startDate)
            ->whereDate('invoice_date', '<=', $endDate)
            ->orderBy('invoice_date')
            ->get();

        $payments = Payment::where('customer_id', $customer->id)
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate)
            ->orderBy('payment_date')
            ->get();

        $transactions = collect()
            ->concat($invoices->map(fn ($inv) => [
                'date' => $inv->invoice_date,
                'type' => 'Invoice',
                'reference' => $inv->invoice_number,
                'description' => 'Invoice',
                'debit' => $inv->total_amount,
                'credit' => 0,
            ]))
            ->concat($payments->map(fn ($pmt) => [
                'date' => $pmt->payment_date,
                'type' => 'Payment',
                'reference' => $pmt->payment_number,
                'description' => $pmt->payment_method->getLabel(),
                'debit' => 0,
                'credit' => $pmt->amount,
            ]))
            ->sortBy('date')
            ->values();

        $runningBalance = $openingBalance;
        $transactionsWithBalance = $transactions->map(function ($trans) use (&$runningBalance) {
            $runningBalance += $trans['debit'] - $trans['credit'];
            $trans['balance'] = $runningBalance;
            $trans['date'] = $trans['date']->toDateString();

            return $trans;
        });

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->display_name,
                'email' => $customer->email,
            ],
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'opening_balance' => $openingBalance,
            'transactions' => $transactionsWithBalance->toArray(),
            'closing_balance' => $runningBalance,
            'total_invoiced' => $invoices->sum('total_amount'),
            'total_payments' => $payments->sum('amount'),
        ];
    }

    public function getRevenueSummary(Carbon $startDate, Carbon $endDate): array
    {
        $invoices = Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->whereDate('invoice_date', '>=', $startDate)
            ->whereDate('invoice_date', '<=', $endDate)
            ->selectRaw('DATE(invoice_date) as date, SUM(total_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $payments = Payment::query()
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate)
            ->selectRaw('DATE(payment_date) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'invoiced' => [
                'daily' => $invoices->pluck('total', 'date')->toArray(),
                'total' => $invoices->sum('total'),
            ],
            'collected' => [
                'daily' => $payments->pluck('total', 'date')->toArray(),
                'total' => $payments->sum('total'),
            ],
        ];
    }

    protected function getAccountBalances(AccountType $type, Carbon $startDate, Carbon $endDate): Collection
    {
        return Account::where('type', $type)
            ->with(['journalEntryLines' => function ($query) use ($startDate, $endDate) {
                $query->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->where('is_posted', true)
                        ->whereDate('entry_date', '>=', $startDate)
                        ->whereDate('entry_date', '<=', $endDate);
                });
            }])
            ->orderBy('code')
            ->get()
            ->map(function ($account) {
                $debits = $account->journalEntryLines->sum('debit_amount');
                $credits = $account->journalEntryLines->sum('credit_amount');

                // Revenue accounts have credit balances, expenses have debit balances
                $balance = $account->type === AccountType::Revenue
                    ? $credits - $debits
                    : $debits - $credits;

                return [
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => $balance,
                ];
            })
            ->filter(fn ($account) => $account['balance'] != 0);
    }

    protected function getAccountBalancesAsOf(AccountType $type, Carbon $asOfDate): Collection
    {
        return Account::where('type', $type)
            ->with(['journalEntryLines' => function ($query) use ($asOfDate) {
                $query->whereHas('journalEntry', function ($q) use ($asOfDate) {
                    $q->where('is_posted', true)
                        ->whereDate('entry_date', '<=', $asOfDate);
                });
            }])
            ->orderBy('code')
            ->get()
            ->map(function ($account) {
                $debits = $account->journalEntryLines->sum('debit_amount');
                $credits = $account->journalEntryLines->sum('credit_amount');

                // Assets and expenses have debit balances; liabilities, equity, revenue have credit balances
                $balance = in_array($account->type, [AccountType::Asset, AccountType::Expense])
                    ? $debits - $credits
                    : $credits - $debits;

                return [
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => $balance,
                ];
            })
            ->filter(fn ($account) => $account['balance'] != 0);
    }

    protected function calculateRetainedEarnings(Carbon $asOfDate): float
    {
        $revenueAccounts = Account::where('type', AccountType::Revenue)->pluck('id');
        $expenseAccounts = Account::where('type', AccountType::Expense)->pluck('id');

        $revenue = JournalEntryLine::query()
            ->whereIn('account_id', $revenueAccounts)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('is_posted', true)
                    ->whereDate('entry_date', '<=', $asOfDate);
            })
            ->selectRaw('SUM(credit_amount) - SUM(debit_amount) as total')
            ->value('total') ?? 0;

        $expenses = JournalEntryLine::query()
            ->whereIn('account_id', $expenseAccounts)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('is_posted', true)
                    ->whereDate('entry_date', '<=', $asOfDate);
            })
            ->selectRaw('SUM(debit_amount) - SUM(credit_amount) as total')
            ->value('total') ?? 0;

        return $revenue - $expenses;
    }
}
