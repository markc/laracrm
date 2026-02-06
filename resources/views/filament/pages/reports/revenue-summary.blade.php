<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex items-end gap-4">
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
            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Invoiced</div>
                    <div class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">${{ number_format($reportData['invoiced']['total'] ?? 0, 2) }}</div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Collected</div>
                    <div class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">${{ number_format($reportData['collected']['total'] ?? 0, 2) }}</div>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Daily Breakdown</h3>
                <table class="mt-3 w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 text-left font-medium text-gray-600 dark:text-gray-400">Date</th>
                            <th class="py-2 text-right font-medium text-gray-600 dark:text-gray-400">Invoiced</th>
                            <th class="py-2 text-right font-medium text-gray-600 dark:text-gray-400">Collected</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $allDates = collect(array_keys($reportData['invoiced']['daily'] ?? []))
                                ->merge(array_keys($reportData['collected']['daily'] ?? []))
                                ->unique()
                                ->sort()
                                ->values();
                        @endphp
                        @forelse($allDates as $date)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ $date }}</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">
                                    {{ isset($reportData['invoiced']['daily'][$date]) ? '$' . number_format($reportData['invoiced']['daily'][$date], 2) : '' }}
                                </td>
                                <td class="py-2 text-right text-gray-900 dark:text-white">
                                    {{ isset($reportData['collected']['daily'][$date]) ? '$' . number_format($reportData['collected']['daily'][$date], 2) : '' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-2 text-gray-500">No activity in this period</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 font-semibold dark:border-gray-600">
                            <td class="py-2 text-gray-900 dark:text-white">Totals</td>
                            <td class="py-2 text-right text-gray-900 dark:text-white">${{ number_format($reportData['invoiced']['total'] ?? 0, 2) }}</td>
                            <td class="py-2 text-right text-gray-900 dark:text-white">${{ number_format($reportData['collected']['total'] ?? 0, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
