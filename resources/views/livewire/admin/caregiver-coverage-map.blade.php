<div class="hc-page py-8 space-y-6">
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

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
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
                    <div id="admin-caregiver-coverage-map" class="mt-3 h-[360px] w-full rounded-xl border border-slate-200 sm:h-[420px] md:h-[480px]"></div>
                </div>
            </div>

            <div class="xl:col-span-4 space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Border legend</h3>
                    <div class="mt-3 space-y-2 text-xs text-slate-600">
                        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" style="background:#67e8f9"></span>Low density</div>
                        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" style="background:#06b6d4"></span>Medium-low</div>
                        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" style="background:#0891b2"></span>Medium</div>
                        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" style="background:#0e7490"></span>High</div>
                        <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" style="background:#155e75"></span>Very high</div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">City borders are colored by density. Count badges show volume per highlighted city.</p>
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

    <script id="admin-caregiver-coverage-map-payload" type="application/json">@json($mapPayload)</script>
    <script>
        (function () {
            const LEAFLET_CSS_PRIMARY = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            const LEAFLET_CSS_FALLBACK = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css';
            const LEAFLET_JS_PRIMARY = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            const LEAFLET_JS_FALLBACK = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js';
            const CENSUS_PLACES_BASE = 'https://tigerweb.geo.census.gov/arcgis/rest/services/Census2020/Places_CouSub_ConCity_SubMCD/MapServer';
            const CENSUS_CITY_LAYERS = [4, 5];

            const showMapError = (container, message) => {
                if (!container) {
                    return;
                }
                container.classList.add('flex', 'items-center', 'justify-center', 'bg-rose-50', 'px-4', 'text-center');
                container.textContent = message;
            };

            const ensureStylesheet = (href) => {
                const existing = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
                    .find((node) => node.href === href);
                if (existing) {
                    return;
                }

                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                link.dataset.hcLeaflet = 'true';
                document.head.appendChild(link);
            };

            const ensureLeaflet = () => {
                if (window.L) {
                    return Promise.resolve(window.L);
                }

                if (window.__hcLeafletPromise) {
                    return window.__hcLeafletPromise;
                }

                ensureStylesheet(LEAFLET_CSS_PRIMARY);

                window.__hcLeafletPromise = new Promise((resolve, reject) => {
                    const loadScript = (src, onError) => {
                        const existingScript = Array.from(document.querySelectorAll('script'))
                            .find((node) => node.src === src);

                        if (existingScript) {
                            existingScript.addEventListener('load', () => resolve(window.L), { once: true });
                            existingScript.addEventListener('error', onError, { once: true });
                            return;
                        }

                        const script = document.createElement('script');
                        script.src = src;
                        script.async = true;
                        script.dataset.hcLeaflet = 'true';
                        script.onload = () => resolve(window.L);
                        script.onerror = onError;
                        document.head.appendChild(script);
                    };

                    loadScript(LEAFLET_JS_PRIMARY, () => {
                        ensureStylesheet(LEAFLET_CSS_FALLBACK);
                        loadScript(LEAFLET_JS_FALLBACK, () => reject(new Error('leaflet_load_failed')));
                    });
                }).finally(() => {
                    // Allow future retries if loading failed.
                    if (!window.L) {
                        window.__hcLeafletPromise = null;
                    }
                });

                return window.__hcLeafletPromise;
            };

            const escapeSqlLiteral = (value) => String(value || '')
                .trim()
                .toUpperCase()
                .replace(/'/g, "''");

            const fetchCityFeature = async (city, stateCode, stateFips) => {
                if (!city || !stateCode || !stateFips) {
                    return null;
                }

                const cityToken = escapeSqlLiteral(city);
                if (!cityToken) {
                    return null;
                }

                for (const layerId of CENSUS_CITY_LAYERS) {
                    const params = new URLSearchParams({
                        where: `UPPER(BASENAME)='${cityToken}' AND STATE='${stateFips}'`,
                        outFields: 'BASENAME,NAME,STATE',
                        returnGeometry: 'true',
                        outSR: '4326',
                        f: 'geojson',
                    });

                    const url = `${CENSUS_PLACES_BASE}/${layerId}/query?${params.toString()}`;

                    try {
                        const response = await fetch(url, { method: 'GET' });
                        if (!response.ok) {
                            continue;
                        }

                        const geojson = await response.json();
                        const features = Array.isArray(geojson?.features) ? geojson.features : [];
                        if (features.length > 0) {
                            return features[0];
                        }
                    } catch (_error) {
                        // Continue silently and fallback later.
                    }
                }

                return null;
            };

            const drawStateFallback = (leaflet, map, statePoints, bounds) => {
                statePoints.forEach((point) => {
                    const lat = Number(point.lat);
                    const lng = Number(point.lng);
                    const count = Number(point.count || 0);

                    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                        return;
                    }

                    bounds.push([lat, lng]);

                    leaflet.circleMarker([lat, lng], {
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

                    const label = leaflet.divIcon({
                        className: 'hc-map-count-badge',
                        html: String(count.toLocaleString()),
                        iconSize: [42, 18],
                        iconAnchor: [21, 9],
                    });

                    leaflet.marker([lat, lng], {
                        icon: label,
                        interactive: false,
                        keyboard: false,
                    }).addTo(map);
                });
            };

            const boot = async () => {
                const container = document.getElementById('admin-caregiver-coverage-map');
                const payloadNode = document.getElementById('admin-caregiver-coverage-map-payload');

                if (!container || !payloadNode) {
                    return;
                }

                if (container.dataset.hcMapBooting === '1') {
                    return;
                }

                container.dataset.hcMapBooting = '1';

                try {
                    if (window.__hcCoverageMap && typeof window.__hcCoverageMap.remove === 'function') {
                        window.__hcCoverageMap.remove();
                        window.__hcCoverageMap = null;
                    }

                    if (container._leaflet_id) {
                        container._leaflet_id = null;
                    }

                    let leaflet = null;
                    try {
                        leaflet = await ensureLeaflet();
                    } catch (_error) {
                        showMapError(container, 'Map libraries could not be loaded. Please retry or check CDN/network access.');
                        return;
                    }

                    if (!leaflet) {
                        showMapError(container, 'Map failed to initialize.');
                        return;
                    }

                    let payload = null;
                    try {
                        payload = JSON.parse(payloadNode.textContent || '{}');
                    } catch (_error) {
                        payload = { points: [] };
                    }

                    const map = leaflet.map(container, {
                        zoomControl: true,
                        minZoom: 3,
                        maxZoom: 10,
                    });

                    window.__hcCoverageMap = map;

                    leaflet.tileLayer(payload.tile_url, {
                        attribution: payload.tile_attribution,
                        maxZoom: 18,
                    }).addTo(map);

                    const bounds = [];
                    const statePoints = Array.isArray(payload.state_points || payload.points) ? (payload.state_points || payload.points) : [];
                    const cityItems = Array.isArray(payload.city_items) ? payload.city_items : [];
                    const stateFips = (payload.state_fips && typeof payload.state_fips === 'object') ? payload.state_fips : {};

                    let highlightedCityCount = 0;

                    for (const city of cityItems) {
                        const stateCode = String(city.state_code || '').toUpperCase();
                        const stateCodeFips = stateFips[stateCode];
                        const feature = await fetchCityFeature(city.city, stateCode, stateCodeFips);
                        if (!feature) {
                            continue;
                        }

                        let geoLayer = null;
                        try {
                            geoLayer = leaflet.geoJSON(feature, {
                                style: {
                                    color: city.color || '#0891b2',
                                    weight: 2,
                                    opacity: 0.95,
                                    fillColor: city.color || '#0891b2',
                                    fillOpacity: 0.14,
                                },
                            }).addTo(map);
                        } catch (_error) {
                            continue;
                        }

                        highlightedCityCount += 1;
                        const label = `${city.city}, ${stateCode}: ${Number(city.count || 0).toLocaleString()}`;
                        geoLayer.bindTooltip(label, { sticky: true });

                        const center = geoLayer.getBounds().getCenter();
                        bounds.push([center.lat, center.lng]);

                        const badgeIcon = leaflet.divIcon({
                            className: 'hc-map-count-badge',
                            html: String(Number(city.count || 0).toLocaleString()),
                            iconSize: [42, 18],
                            iconAnchor: [21, 9],
                        });

                        leaflet.marker(center, {
                            icon: badgeIcon,
                            interactive: false,
                            keyboard: false,
                        }).addTo(map);
                    }

                    if (highlightedCityCount === 0) {
                        drawStateFallback(leaflet, map, statePoints, bounds);
                    }

                    if (bounds.length > 0) {
                        map.fitBounds(bounds, { padding: [26, 26], maxZoom: 7 });
                        return;
                    }

                    map.setView([39.5, -98.35], 4);
                } finally {
                    container.dataset.hcMapBooting = '0';
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
