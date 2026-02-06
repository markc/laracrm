<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex items-end gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">As of Date</label>
                <input type="date" wire:model.live="asOfDate" class="mt-1 block rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm">
            </div>
        </div>

        @if(!empty($reportData))
            @foreach(['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $label)
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $label }}</h3>
                    <table class="mt-3 w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="py-2 text-left font-medium text-gray-600 dark:text-gray-400">Account</th>
                                <th class="py-2 text-right font-medium text-gray-600 dark:text-gray-400">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reportData[$key]['accounts'] ?? [] as $account)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 text-gray-700 dark:text-gray-300">{{ $account['code'] }} — {{ $account['name'] }}</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white">${{ number_format($account['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="py-2 text-gray-500">No accounts with balances</td></tr>
                            @endforelse
                            @if($key === 'equity' && isset($reportData['equity']['retained_earnings']))
                                <tr class="border-b border-gray-100 dark:border-gray-800 italic">
                                    <td class="py-2 text-gray-700 dark:text-gray-300">Retained Earnings</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white">${{ number_format($reportData['equity']['retained_earnings'], 2) }}</td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 font-semibold dark:border-gray-600">
                                <td class="py-2 text-gray-900 dark:text-white">Total {{ $label }}</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">${{ number_format($reportData[$key]['total'] ?? 0, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endforeach

            <div class="rounded-xl p-6 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 {{ ($reportData['balanced'] ?? false) ? 'bg-green-50 dark:bg-green-950' : 'bg-red-50 dark:bg-red-950' }}">
                <div class="flex items-center justify-between">
                    <span class="font-semibold {{ ($reportData['balanced'] ?? false) ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200' }}">
                        {{ ($reportData['balanced'] ?? false) ? 'Balance Sheet is balanced' : 'Balance Sheet is NOT balanced' }}
                    </span>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
