<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex items-end gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">As of Date</label>
                <input type="date" wire:model.live="asOfDate" class="mt-1 block rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm">
            </div>
        </div>

        @if(!empty($reportData))
            @php
                $buckets = [
                    'current' => 'Current',
                    '1_30' => '1-30 Days',
                    '31_60' => '31-60 Days',
                    '61_90' => '61-90 Days',
                    'over_90' => '90+ Days',
                ];
            @endphp

            {{-- Summary bar --}}
            <div class="grid grid-cols-5 gap-4">
                @foreach($buckets as $key => $label)
                    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                            ${{ number_format($reportData['aging'][$key]['total'] ?? 0, 2) }}
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Detail table per bucket --}}
            @foreach($buckets as $key => $label)
                @if(!empty($reportData['aging'][$key]['invoices']))
                    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $label }}</h3>
                        <table class="mt-3 w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="py-2 text-left font-medium text-gray-600 dark:text-gray-400">Invoice</th>
                                    <th class="py-2 text-left font-medium text-gray-600 dark:text-gray-400">Customer</th>
                                    <th class="py-2 text-left font-medium text-gray-600 dark:text-gray-400">Due Date</th>
                                    <th class="py-2 text-right font-medium text-gray-600 dark:text-gray-400">Days</th>
                                    <th class="py-2 text-right font-medium text-gray-600 dark:text-gray-400">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reportData['aging'][$key]['invoices'] as $invoice)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="py-2 text-gray-700 dark:text-gray-300">{{ $invoice['invoice_number'] }}</td>
                                        <td class="py-2 text-gray-700 dark:text-gray-300">{{ $invoice['customer'] }}</td>
                                        <td class="py-2 text-gray-700 dark:text-gray-300">{{ $invoice['due_date'] }}</td>
                                        <td class="py-2 text-right text-gray-700 dark:text-gray-300">{{ $invoice['days_overdue'] }}</td>
                                        <td class="py-2 text-right text-gray-900 dark:text-white">${{ number_format($invoice['balance_due'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endforeach

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between">
                    <span class="text-lg font-bold text-gray-900 dark:text-white">Total Outstanding</span>
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">${{ number_format($reportData['grand_total'] ?? 0, 2) }}</span>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
