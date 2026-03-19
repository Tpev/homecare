<div class="hc-page py-8 space-y-6">
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    />

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Caregiver Coverage Map</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $metricMeta['label'] }} since {{ $start->format('M d, Y') }}.
                    </p>
                </div>

                <form method="GET" action="{{ route('admin.analytics.caregiver-map') }}" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <label class="text-xs text-slate-500">
                        Range
                        <select name="days" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-800" onchange="this.form.submit()">
                            @foreach($daysOptions as $option)
                                <option value="{{ $option }}" @selected((int) $days === (int) $option)>Last {{ $option }} days</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="text-xs text-slate-500">
                        Metric
                        <select name="metric" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-800" onchange="this.form.submit()">
                            @foreach($metricOptions as $option)
                                <option value="{{ $option['value'] }}" @selected($metric === $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <noscript>
                        <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Apply</button>
                    </noscript>
                </form>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">{{ $metricMeta['short_label'] }}</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($summary['total']) }}</p>
                <p class="text-xs text-slate-500">Total in selected range</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">States covered</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($summary['states_with_coverage']) }}</p>
                <p class="text-xs text-slate-500">With at least one data point</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Top state</p>
                @if($summary['top_state'])
                    <p class="mt-1 text-lg font-black text-slate-900">{{ $summary['top_state']['name'] }}</p>
                    <p class="text-xs text-slate-500">{{ number_format($summary['top_state']['count']) }} {{ strtolower($metricMeta['short_label']) }}</p>
                @else
                    <p class="mt-1 text-lg font-black text-slate-900">—</p>
                    <p class="text-xs text-slate-500">No data yet</p>
                @endif
            </div>
            <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-4">
                <p class="text-xs uppercase tracking-[0.14em] text-cyan-700">Peak state count</p>
                <p class="mt-1 text-3xl font-black text-cyan-900">{{ number_format($summary['peak_count']) }}</p>
                <p class="text-xs text-cyan-700">Used for color + radius scaling</p>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-12">
            <div class="xl:col-span-8">
                <div class="rounded-2xl border border-slate-200 bg-white p-3 md:p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-slate-900">US coverage intensity map</h2>
                        <p class="text-xs text-slate-500">Provider: {{ $mapPayload['provider'] }}</p>
                    </div>
                    <div id="admin-caregiver-coverage-map" class="mt-3 h-[480px] w-full rounded-xl border border-slate-200"></div>
                    @if($mapPayload['provider'] === 'OpenStreetMap')
                        <p class="mt-2 text-xs text-amber-700">
                            Tip: set <code>MAPTILER_KEY</code> for production tile SLA and higher request limits.
                        </p>
                    @endif
                </div>
            </div>

            <div class="xl:col-span-4 space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Color legend</h3>
                    <div class="mt-3 space-y-2 text-xs text-slate-600">
                        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" style="background:#67e8f9"></span>Low density</div>
                        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" style="background:#06b6d4"></span>Medium-low</div>
                        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" style="background:#0891b2"></span>Medium</div>
                        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" style="background:#0e7490"></span>High</div>
                        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" style="background:#155e75"></span>Very high</div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Each circle includes a count badge. Bigger + darker means more volume.</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Top cities</h3>
                    <div class="mt-3 space-y-2">
                        @forelse($cityRows as $row)
                            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-sm font-medium text-slate-900">{{ $row->city }}, {{ $row->state_code }}</p>
                                <p class="text-sm font-black text-slate-900">{{ number_format((int) $row->total) }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-600">No city-level data in this range.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </x-card>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
    ></script>
    <script id="admin-caregiver-coverage-map-payload" type="application/json">@json($mapPayload)</script>
    <script>
        (function () {
            const boot = () => {
                const container = document.getElementById('admin-caregiver-coverage-map');
                const payloadNode = document.getElementById('admin-caregiver-coverage-map-payload');

                if (!container || !payloadNode || typeof L === 'undefined') {
                    return;
                }

                if (window.__hcCoverageMap && typeof window.__hcCoverageMap.remove === 'function') {
                    window.__hcCoverageMap.remove();
                }

                let payload = null;
                try {
                    payload = JSON.parse(payloadNode.textContent || '{}');
                } catch (_error) {
                    payload = { points: [] };
                }

                const map = L.map(container, {
                    zoomControl: true,
                    minZoom: 3,
                    maxZoom: 10,
                });

                window.__hcCoverageMap = map;

                L.tileLayer(payload.tile_url, {
                    attribution: payload.tile_attribution,
                    maxZoom: 18,
                }).addTo(map);

                const points = Array.isArray(payload.points) ? payload.points : [];
                const bounds = [];

                points.forEach((point) => {
                    const lat = Number(point.lat);
                    const lng = Number(point.lng);
                    const count = Number(point.count || 0);

                    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                        return;
                    }

                    bounds.push([lat, lng]);

                    L.circleMarker([lat, lng], {
                        radius: Number(point.radius || 16),
                        color: '#0f172a',
                        weight: 1,
                        fillColor: point.color || '#06b6d4',
                        fillOpacity: 0.78,
                    })
                        .bindTooltip(`${point.state_name}: ${count.toLocaleString()}`, {
                            direction: 'top',
                            sticky: true,
                        })
                        .addTo(map);

                    const label = L.divIcon({
                        className: 'hc-map-count-badge',
                        html: `<span>${count.toLocaleString()}</span>`,
                        iconSize: [42, 18],
                        iconAnchor: [21, 9],
                    });

                    L.marker([lat, lng], {
                        icon: label,
                        interactive: false,
                        keyboard: false,
                    }).addTo(map);
                });

                if (bounds.length > 0) {
                    map.fitBounds(bounds, { padding: [26, 26], maxZoom: 6 });
                } else {
                    map.setView([39.5, -98.35], 4);
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot, { once: true });
            } else {
                boot();
            }

            if (!window.__hcCoverageMapListenerAttached) {
                document.addEventListener('livewire:navigated', boot);
                window.__hcCoverageMapListenerAttached = true;
            }
        })();
    </script>
</div>
