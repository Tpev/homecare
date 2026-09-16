<section class="mt-6 min-w-0 rounded-2xl border border-slate-200 bg-white p-4 md:p-5" aria-labelledby="customer-hours-heading" data-testid="customer-booked-hours">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="customer-hours-heading" class="text-base font-semibold text-slate-900">Booked hours by customer</h2>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">Recorded hours from completed visits, grouped by visit completion date. Includes only customers with at least one completed visit in the selected date range.</p>
        </div>
        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">{{ $groupingLabel }} · {{ number_format($customerHours['visit_count']) }} completed visits</span>
    </div>

    <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl bg-emerald-50 p-4">
            <dt class="text-xs font-medium text-emerald-800">Completed hours</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-emerald-950" data-testid="customer-hours-total">{{ number_format($customerHours['total_hours'], 2) }} <span class="text-sm font-medium">h</span></dd>
        </div>
        <div class="rounded-xl bg-slate-50 p-4">
            <dt class="text-xs font-medium text-slate-600">Customers with completed visits</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-slate-900" data-testid="customer-hours-count">{{ number_format($customerHours['customer_count']) }}</dd>
        </div>
        <div class="rounded-xl bg-slate-50 p-4">
            <dt class="text-xs font-medium text-slate-600">Average hours per customer</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-slate-900" data-testid="customer-hours-average">{{ number_format($customerHours['average_hours_per_customer'], 2) }} <span class="text-sm font-medium">h</span></dd>
            <p class="mt-1 text-xs text-slate-500">Across the selected date range</p>
        </div>
        <div class="rounded-xl bg-slate-50 p-4">
            <dt class="text-xs font-medium text-slate-600">Average hours per customer / {{ $customerHoursPeriod }}</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-slate-900" data-testid="customer-hours-period-average">{{ number_format($customerHours['average_hours_per_customer_period'], 2) }} <span class="text-sm font-medium">h</span></dd>
            <p class="mt-1 text-xs text-slate-500">Includes periods with zero hours</p>
        </div>
    </dl>

    @if ($customerHours['customers'] !== [])
        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-600" tabindex="0" role="region" aria-label="Customer hours table; scroll horizontally to see all periods">
            <table class="min-w-full divide-y divide-slate-200 text-sm tabular-nums" data-testid="customer-hours-table">
                <caption class="sr-only">{{ $groupingLabel }} completed hours per customer. All hour values are decimal hours.</caption>
                <thead class="bg-slate-50 text-xs text-slate-600">
                    <tr>
                        <th scope="col" class="sticky left-0 z-10 w-40 min-w-40 max-w-40 bg-slate-50 px-4 py-3 text-left sm:w-48 sm:min-w-48 sm:max-w-64">Customer</th>
                        @foreach ($customerHours['periods'] as $period)
                            <th scope="col" class="w-28 min-w-28 px-4 py-3 text-right sm:w-auto sm:min-w-32 sm:whitespace-nowrap">
                                {{ $period['label'] }}
                                @if ($period['partial']) <span class="mt-1 block text-[11px] font-normal text-slate-500">Partial {{ $customerHoursPeriod }}</span> @endif
                            </th>
                        @endforeach
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">Total hours</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">Avg / {{ $customerHoursPeriod }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($customerHours['customers'] as $customer)
                        <tr wire:key="customer-hours-{{ $customer['key'] }}" data-testid="customer-hours-row">
                            <th scope="row" class="sticky left-0 z-10 w-40 min-w-40 max-w-40 bg-white px-4 py-3 text-left font-normal sm:w-48 sm:min-w-48 sm:max-w-64">
                                @if ($customer['user_id'])
                                    <a href="{{ route('admin.users.show', $customer['user_id']) }}" wire:navigate class="font-semibold text-emerald-800 underline decoration-emerald-200 underline-offset-4 hover:decoration-emerald-800">{{ $customer['name'] }}</a>
                                @else
                                    <span class="font-semibold text-slate-900">{{ $customer['name'] }}</span>
                                @endif
                                <span class="mt-1 block break-all text-xs text-slate-500">{{ $customer['email'] }}</span>
                                <span class="mt-1 block text-xs text-slate-500">{{ $customer['visits'] }} {{ \Illuminate\Support\Str::plural('visit', $customer['visits']) }}</span>
                            </th>
                            @foreach ($customerHours['periods'] as $period)
                                <td class="whitespace-nowrap px-4 py-3 text-right {{ $customer['hours_by_period'][$period['key']] > 0 ? 'text-slate-800' : 'text-slate-400' }}">{{ number_format($customer['hours_by_period'][$period['key']], 2) }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ number_format($customer['total_hours'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ number_format($customer['average_hours_per_period'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold text-slate-900">
                    <tr>
                        <th scope="row" class="sticky left-0 z-10 bg-slate-50 px-4 py-3 text-left">All customers · total</th>
                        @foreach ($customerHours['periods'] as $period)
                            <td class="px-4 py-3 text-right">{{ number_format($customerHours['period_hours'][$period['key']], 2) }}</td>
                        @endforeach
                        <td class="px-4 py-3 text-right">{{ number_format($customerHours['total_hours'], 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($customerHours['average_hours_per_period'], 2) }}</td>
                    </tr>
                    <tr class="text-emerald-800">
                        <th scope="row" class="sticky left-0 z-10 bg-slate-50 px-4 py-3 text-left">Average per customer</th>
                        @foreach ($customerHours['periods'] as $period)
                            <td class="px-4 py-3 text-right">{{ number_format($customerHours['period_average_hours'][$period['key']], 2) }}</td>
                        @endforeach
                        <td class="px-4 py-3 text-right">{{ number_format($customerHours['average_hours_per_customer'], 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($customerHours['average_hours_per_customer_period'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="mt-3 text-xs text-slate-500">Hours are shown as decimals: 1.50 = 1 hour 30 minutes. Each family account counts as one customer. Averages use all {{ $customerHours['customer_count'] }} customers shown and all {{ count($customerHours['periods']) }} selected periods, including zero-hour and partial periods.</p>
    @else
        <p class="mt-4 rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">No customers have completed visits with recorded hours in this date range.</p>
    @endif
</section>
