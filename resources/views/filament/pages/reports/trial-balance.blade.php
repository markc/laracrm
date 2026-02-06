<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex items-end gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">As of Date</label>
                <input type="date" wire:model.live="asOfDate" class="mt-1 block rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm">
            </div>
        </div>

        @if(!empty($reportData))
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 text-left font-medium text-gray-600 dark:text-gray-400">Code</th>
                            <th class="py-2 text-left font-medium text-gray-600 dark:text-gray-400">Account</th>
                            <th class="py-2 text-left font-medium text-gray-600 dark:text-gray-400">Type</th>
                            <th class="py-2 text-right font-medium text-gray-600 dark:text-gray-400">Debit</th>
                            <th class="py-2 text-right font-medium text-gray-600 dark:text-gray-400">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData['accounts'] ?? [] as $account)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 font-mono text-gray-700 dark:text-gray-300">{{ $account['code'] }}</td>
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ $account['name'] }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ ucfirst($account['type']) }}</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">
                                    {{ $account['debit_balance'] > 0 ? '$' . number_format($account['debit_balance'], 2) : '' }}
                                </td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">
                                    {{ $account['credit_balance'] > 0 ? '$' . number_format($account['credit_balance'], 2) : '' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-2 text-gray-500">No accounts with balances</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 font-semibold dark:border-gray-600">
                            <td colspan="3" class="py-2 text-gray-900 dark:text-white">Totals</td>
                            <td class="py-2 text-right text-gray-900 dark:text-white">${{ number_format($reportData['total_debits'] ?? 0, 2) }}</td>
                            <td class="py-2 text-right text-gray-900 dark:text-white">${{ number_format($reportData['total_credits'] ?? 0, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="rounded-xl p-6 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 {{ ($reportData['balanced'] ?? false) ? 'bg-green-50 dark:bg-green-950' : 'bg-red-50 dark:bg-red-950' }}">
                <span class="font-semibold {{ ($reportData['balanced'] ?? false) ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200' }}">
                    {{ ($reportData['balanced'] ?? false) ? 'Trial Balance is balanced' : 'Trial Balance is NOT balanced — difference: $' . number_format(abs(($reportData['total_debits'] ?? 0) - ($reportData['total_credits'] ?? 0)), 2) }}
                </span>
            </div>
        @endif
    </div>
</x-filament-panels::page>
