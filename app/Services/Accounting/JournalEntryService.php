<?php

namespace App\Services\Accounting;

use App\Exceptions\UnbalancedEntryException;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    public function createEntry(array $data): JournalEntry
    {
        $this->validateBalance($data['lines']);

        return DB::transaction(function () use ($data) {
            $entry = JournalEntry::create([
                'entry_number' => $this->generateEntryNumber(),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($data['lines'] as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit_amount' => $line['debit_amount'] ?? 0,
                    'credit_amount' => $line['credit_amount'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $entry->fresh(['lines.account']);
        });
    }

    public function postEntry(JournalEntry $entry): bool
    {
        if ($entry->is_posted) {
            return false;
        }

        return DB::transaction(function () use ($entry) {
            $entry->update([
                'is_posted' => true,
                'posted_at' => now(),
                'approved_by' => auth()->id(),
                'is_locked' => true,
            ]);

            return true;
        });
    }

    public function reverseEntry(JournalEntry $entry, string $reason): JournalEntry
    {
        if (! $entry->is_posted) {
            throw new \Exception('Cannot reverse unposted entry');
        }

        return DB::transaction(function () use ($entry, $reason) {
            $reversingLines = $entry->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'debit_amount' => $line->credit_amount,
                'credit_amount' => $line->debit_amount,
                'description' => "Reversal: {$line->description}",
            ])->toArray();

            $reversingEntry = $this->createEntry([
                'entry_date' => now(),
                'description' => "Reversal of {$entry->entry_number}: {$reason}",
                'lines' => $reversingLines,
            ]);

            $entry->update(['reversed_by_id' => $reversingEntry->id]);
            $this->postEntry($reversingEntry);

            return $reversingEntry;
        });
    }

    public function getAccountBalance(Account $account, ?Carbon $asOfDate = null): float
    {
        $query = $account->journalEntryLines()
            ->whereHas('journalEntry', fn ($q) => $q->where('is_posted', true));

        if ($asOfDate) {
            $query->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOfDate));
        }

        $debits = (clone $query)->sum('debit_amount');
        $credits = (clone $query)->sum('credit_amount');

        return $account->normal_balance === 'debit'
            ? $debits - $credits
            : $credits - $debits;
    }

    protected function validateBalance(array $lines): void
    {
        $totalDebits = collect($lines)->sum('debit_amount');
        $totalCredits = collect($lines)->sum('credit_amount');

        // Compare with 2 decimal precision (difference less than 0.01)
        if (abs((float) $totalDebits - (float) $totalCredits) >= 0.01) {
            throw new UnbalancedEntryException(
                "Entry is unbalanced: Debits ({$totalDebits}) != Credits ({$totalCredits})"
            );
        }
    }

    protected function generateEntryNumber(): string
    {
        $prefix = 'JE-'.now()->format('Ym').'-';
        $lastEntry = JournalEntry::where('entry_number', 'like', $prefix.'%')
            ->orderBy('entry_number', 'desc')
            ->first();

        $nextNumber = $lastEntry
            ? (int) substr($lastEntry->entry_number, -4) + 1
            : 1;

        return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
