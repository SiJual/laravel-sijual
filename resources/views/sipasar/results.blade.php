<x-layouts.app title="Laporan Riset Pasar — SiPasar" activeNav="sipasar">
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.css" rel="stylesheet">
    <style>
        .mapboxgl-ctrl-logo, .mapboxgl-ctrl-attrib { display: none !important; }
    </style>

    <div class="p-6 lg:p-8 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold font-display text-on-surface">Hasil Riset Pasar: {{ $analysis->location_query }}</h1>
                <p class="text-sm text-on-surface-variant font-medium">Analisis geodemografis, rating kompetitor, dan persentase kesesuaian pasar.</p>
            </div>
            <a href="{{ route('sipasar.landing') }}" class="text-sm font-semibold text-primary hover:underline">← Riset Lokasi Lain</a>
        </div>

        {{-- Score Card & Demographics Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-ui.market-fit-score-card
                :score="$analysis->market_fit_score"
                :radius="$analysis->radius_km"
                :competitorCount="$analysis->competitors->count()"
                :density="$analysis->demographic_data['density'] ?? 'Tinggi'"
            />

            {{-- Demographic Panel --}}
            <div class="lg:col-span-2 bg-surface p-6 rounded-lg border border-border shadow-card space-y-4">
                <h3 class="text-lg font-bold font-display text-on-surface">Profil Demografi (Mapbox & Overpass API)</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center text-xs">
                    <div class="p-3 bg-surface-alt rounded border border-border/50">
                        <span class="block text-on-surface-variant font-semibold">Estimasi Populasi</span>
                        <span class="text-lg font-bold text-on-surface">{{ number_format($analysis->demographic_data['population'] ?? 48500) }} jiwa</span>
                    </div>
                    <div class="p-3 bg-surface-alt rounded border border-border/50">
                        <span class="block text-on-surface-variant font-semibold">Rata-rata Pendapatan</span>
                        <span class="text-lg font-bold text-on-surface">Rp {{ number_format($analysis->demographic_data['avg_monthly_income'] ?? 6500000) }}</span>
                    </div>
                    <div class="p-3 bg-surface-alt rounded border border-border/50">
                        <span class="block text-on-surface-variant font-semibold">Kepadatan</span>
                        <span class="text-lg font-bold text-on-surface">{{ $analysis->demographic_data['density'] ?? 'Sedang' }}</span>
                    </div>
                    <div class="p-3 bg-surface-alt rounded border border-border/50">
                        <span class="block text-on-surface-variant font-semibold">Sumber Data</span>
                        <span class="text-lg font-bold text-on-surface uppercase">OSM / Mapbox</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Interactive Mapbox Visualization --}}
        <div class="bg-surface border border-border rounded-lg p-4 shadow-card space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold font-display text-on-surface">Peta Sebaran Kompetitor & Radius Riset</h3>
                <span class="text-xs font-semibold text-primary px-2.5 py-1 bg-primary/10 rounded-full">Radius: {{ $analysis->radius_km }} km</span>
            </div>
            <div id="results-map" class="w-full h-[350px] lg:h-[500px] rounded-md border border-border/80 overflow-hidden relative"></div>
        </div>

        {{-- Competitors List --}}
        <div class="bg-surface border border-border rounded-lg p-6 shadow-card space-y-4">
            <h3 class="text-lg font-bold font-display text-on-surface">Kompetitor Terdeteksi ({{ $analysis->competitors->count() }})</h3>

            @if($analysis->competitors->isEmpty())
                <div class="p-6 text-center text-on-surface-variant text-sm">Tidak ada kompetitor langsung terdeteksi dalam radius riset.</div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($analysis->competitors as $comp)
                        <div class="p-4 bg-surface-alt border border-border/60 rounded-md space-y-2">
                            <div class="flex items-start justify-between">
                                <h4 class="font-bold text-sm text-on-surface">{{ $comp->name }}</h4>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $comp->sentiment === 'positive' ? 'bg-success-bg text-success' : 'bg-muted text-on-surface-variant' }}">
                                    {{ $comp->sentiment }}
                                </span>
                            </div>
                            <p class="text-xs text-on-surface-variant">{{ $comp->business_type }} • {{ $comp->address }}</p>
                            <div class="flex items-center gap-2 pt-2 text-xs font-semibold text-on-surface">
                                <span class="text-amber-500">★ {{ $comp->rating }}</span>
                                <span>({{ $comp->review_count }} ulasan)</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.js"></script>
    <script>
        mapboxgl.accessToken = '{{ config("services.mapbox.key") }}';
        
        const centerLng = {{ $analysis->longitude ?? 106.8272 }};
        const centerLat = {{ $analysis->latitude ?? -6.1751 }};
        const radiusKm = {{ $analysis->radius_km ?? 2.5 }};

        const map = new mapboxgl.Map({
            container: 'results-map',
            style: 'mapbox://styles/mapbox/light-v11',
            center: [centerLng, centerLat],
            zoom: 13
        });

        map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'bottom-right');

        const bounds = new mapboxgl.LngLatBounds();
        bounds.extend([centerLng, centerLat]);

        // Center Marker (User Target Location)
        const centerEl = document.createElement('div');
        centerEl.className = 'w-5 h-5 bg-primary rounded-full shadow-lg border-2 border-white flex items-center justify-center';
        centerEl.innerHTML = '<div class="w-1.5 h-1.5 bg-white rounded-full"></div>';
        
        new mapboxgl.Marker({ element: centerEl })
            .setLngLat([centerLng, centerLat])
            .setPopup(new mapboxgl.Popup({ offset: 12 }).setHTML('<p class="text-xs font-bold text-primary">Pusat Target: {{ addslashes($analysis->location_query) }}</p>'))
            .addTo(map);

        // Competitor Markers
        @foreach($analysis->competitors as $comp)
        {
            const compEl = document.createElement('div');
            compEl.className = 'w-3.5 h-3.5 bg-on-surface-variant rounded-full opacity-80 border-2 border-white shadow-sm';
            
            const lng = {{ $comp->longitude }};
            const lat = {{ $comp->latitude }};
            bounds.extend([lng, lat]);

            new mapboxgl.Marker({ element: compEl })
                .setLngLat([lng, lat])
                .setPopup(new mapboxgl.Popup({ offset: 10 }).setHTML(
                    '<div class="p-1 max-w-xs">' +
                    '<p class="text-xs font-bold text-on-surface">{{ addslashes($comp->name) }}</p>' +
                    '<p class="text-[10px] text-on-surface-variant">{{ addslashes($comp->business_type) }}</p>' +
                    '<p class="text-[10px] font-semibold text-amber-500 mt-1">★ {{ $comp->rating }} ({{ $comp->review_count }} ulasan)</p>' +
                    '</div>'
                ))
                .addTo(map);
        }
        @endforeach

        // Fit bounds with padding so all markers are visible
        if ({{ $analysis->competitors->count() }} > 0) {
            map.fitBounds(bounds, { padding: 60, maxZoom: 15 });
        }

        // Draw transparent radius circle around target
        map.on('load', () => {
            const points = 64;
            const coords = { latitude: centerLat, longitude: centerLng };
            
            const km = radiusKm;
            const ret = [];
            const distanceX = km/(111.320*Math.cos(coords.latitude*Math.PI/180));
            const distanceY = km/110.574;
            
            let theta, x, y;
            for(let i=0; i<points; i++) {
                theta = (i/points)*(2*Math.PI);
                x = distanceX*Math.cos(theta);
                y = distanceY*Math.sin(theta);
                ret.push([coords.longitude+x, coords.latitude+y]);
            }
            ret.push(ret[0]);
            
            map.addSource('radius-polygon', {
                'type': 'geojson',
                'data': {
                    'type': 'Feature',
                    'geometry': {
                        'type': 'Polygon',
                        'coordinates': [ret]
                    }
                }
            });
            
            map.addLayer({
                'id': 'radius-fill',
                'type': 'fill',
                'source': 'radius-polygon',
                'layout': {},
                'paint': {
                    'fill-color': '#9D3D2B',
                    'fill-opacity': 0.15
                }
            });
            
            map.addLayer({
                'id': 'radius-stroke',
                'type': 'line',
                'source': 'radius-polygon',
                'layout': {},
                'paint': {
                    'line-color': '#9D3D2B',
                    'line-width': 1.5,
                    'line-opacity': 0.4
                }
            });
        });
    </script>
    @endpush
</x-layouts.app>
