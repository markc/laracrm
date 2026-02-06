<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex items-end gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Customer</label>
                <select wire:model.live="customerId" class="mt-1 block rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm">
                    <option value="">Select a customer...</option>
                    @foreach($this->getCustomerOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                <input type="date" wire:model.live="startDate" class="mt-1 block rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">End Date</label>
                <input type="date" wire:model.live="endDate" class="mt-1 block rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm">
            </div>
        </div>

        @if(!empty($reportData))
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $reportData['customer']['name'] ?? '' }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $reportData['customer']['email'] ?? '' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $reportData['period']['start'] ?? '' }} to {{ $reportData['period']['end'] ?? '' }}</p>
                    </div>
                </div>

                <div class="mb-4 rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Opening Balance:</span>
                    <span class="ml-2 font-semibold text-gray-900 dark:text-white">${{ number_format($reportData['opening_balance'] ?? 0, 2) }}</span>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 text-left font-medium text-gray-600 dark:text-gray-400">Date</th>
                            <th class="py-2 text-left font-medium text-gray-600 dark:text-gray-400">Type</th>
                            <th class="py-2 text-left font-medium text-gray-600 dark:text-gray-400">Reference</th>
                            <th class="py-2 text-right font-medium text-gray-600 dark:text-gray-400">Debit</th>
                            <th class="py-2 text-right font-medium text-gray-600 dark:text-gray-400">Credit</th>
                            <th class="py-2 text-right font-medium text-gray-600 dark:text-gray-400">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData['transactions'] ?? [] as $transaction)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ $transaction['date'] }}</td>
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ $transaction['type'] }}</td>
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ $transaction['reference'] }}</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">
                                    {{ $transaction['debit'] > 0 ? '$' . number_format($transaction['debit'], 2) : '' }}
                                </td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">
                                    {{ $transaction['credit'] > 0 ? '$' . number_format($transaction['credit'], 2) : '' }}
                                </td>
                                <td class="py-2 text-right font-medium text-gray-900 dark:text-white">${{ number_format($transaction['balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-2 text-gray-500">No transactions in this period</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4 grid grid-cols-3 gap-4 rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Total Invoiced</span>
                        <p class="font-semibold text-gray-900 dark:text-white">${{ number_format($reportData['total_invoiced'] ?? 0, 2) }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Total Payments</span>
                        <p class="font-semibold text-gray-900 dark:text-white">${{ number_format($reportData['total_payments'] ?? 0, 2) }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Closing Balance</span>
                        <p class="font-semibold text-gray-900 dark:text-white">${{ number_format($reportData['closing_balance'] ?? 0, 2) }}</p>
                    </div>
                </div>
            </div>
        @elseif(!$customerId)
            <div class="rounded-xl bg-white p-6 text-center text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                Select a customer to view their statement.
            </div>
        @endif
    </div>
</x-filament-panels::page>
