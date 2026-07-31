<x-layouts.app title="SiPasar — Riset Pasar Geodemografis" activeNav="sipasar">
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.css" rel="stylesheet">
    <style>
        body { overflow: hidden; }
        /* Hide mapbox logo & telemetry to keep it clean like the design */
        .mapboxgl-ctrl-logo, .mapboxgl-ctrl-attrib { display: none !important; }
        /* Push zoom controls up so they don't get hidden by bottom navbar or screen edges */
        .mapboxgl-ctrl-bottom-right { bottom: 6rem !important; right: 1.5rem !important; }
    </style>

    {{-- Mapbox Container --}}
    <div id="map" class="absolute top-0 left-0 w-full h-screen z-0"></div>

    {{-- Floating Market Filters (Left) --}}
    <div class="absolute top-22 left-6 z-10 bg-white/95 backdrop-blur-md w-full max-w-sm p-5 rounded-xl shadow-lg border border-border">
            <div class="flex items-center gap-2 mb-4">
                <svg class="size-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                <h2 class="font-bold text-on-surface text-lg">Market Filters</h2>
            </div>

            <form action="{{ route('sipasar.analyze') }}" method="POST" id="analyze-form" class="space-y-4"
                  x-data="{ submitting: false, step: 0, startTime: 0, messages: [
                      'Menganalisis kepadatan & demografi area...',
                      'Menyintesis data kompetitor dengan AI...',
                      'Menyusun skor kelayakan & peta pasar...'
                  ] }"
                  @submit="submitting = true; startTime = Date.now(); setInterval(() => { step = Math.min(2, Math.floor((Date.now() - startTime) / 3500)); }, 500)">
                @csrf
                <div>
                    <label for="location_query" class="block text-xs font-semibold text-on-surface-variant mb-1.5">Location Search</label>
                    <div class="relative" x-data="{ query: '{{ old('location_query', 'Jakarta Selatan') }}', selectedLat: '{{ old('latitude', $latestAnalysis?->latitude ?? ($profile->latitude ?? -6.2444)) }}', selectedLng: '{{ old('longitude', $latestAnalysis?->longitude ?? ($profile->longitude ?? 106.8006)) }}', results: [], open: false, loading: false, timer: null }" 
                         x-init="$watch('query', value => {
                             clearTimeout(timer);
                             if (value.length > 2) {
                                 loading = true;
                                 timer = setTimeout(() => {
                                     fetch(`https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(value)}.json?access_token={{ config('services.mapbox.key') }}&country=id&limit=5`)
                                         .then(res => res.json())
                                         .then(data => {
                                             results = data.features || [];
                                             open = true;
                                             loading = false;
                                         });
                                 }, 400);
                             } else {
                                 results = [];
                                 open = false;
                             }
                         })">
                        <input type="hidden" name="latitude" x-model="selectedLat">
                        <input type="hidden" name="longitude" x-model="selectedLng">
                        <svg class="absolute left-3 top-2.5 size-4 text-on-surface-variant" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" id="location_query" name="location_query" x-model="query" required
                               @focus="open = results.length > 0"
                               @click.outside="open = false"
                               placeholder="e.g. Jakarta Selatan"
                               class="w-full pl-9 pr-3 py-2 bg-white border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 outline-none" autocomplete="off">
                        
                        {{-- Autocomplete Dropdown --}}
                        <div x-show="open && results.length > 0" x-transition class="absolute left-0 right-0 top-full mt-1 bg-white border border-border rounded-md shadow-lg max-h-48 overflow-y-auto z-50 text-left">
                            <template x-for="item in results" :key="item.id">
                                <div @click="query = item.place_name; open = false; if (item.center) { selectedLng = item.center[0]; selectedLat = item.center[1]; } if (window.map && window.marker && item.center) { window.map.flyTo({ center: item.center, zoom: 14 }); window.marker.setLngLat(item.center); }"
                                     class="px-3 py-2 text-xs text-on-surface hover:bg-surface-alt cursor-pointer border-b border-border last:border-0 truncate">
                                    <span x-text="item.place_name"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label for="radius_km" class="block text-xs font-semibold text-on-surface-variant">Analysis Radius</label>
                        <span class="text-xs font-bold text-primary" id="radius_val">2.5 km</span>
                    </div>
                    <input type="range" id="radius_km" name="radius_km" min="0.5" max="5.0" step="0.5" value="2.5" class="w-full accent-primary" oninput="document.getElementById('radius_val').innerText = this.value + ' km'">
                    <div class="flex justify-between text-[10px] text-on-surface-variant mt-1">
                        <span>1km</span>
                        <span>5km</span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant mb-2">Competitor Categories</label>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-3 py-1 bg-primary/10 text-primary border border-primary/20 rounded-full text-xs font-semibold flex items-center gap-1"><svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Food & Beverage</span>
                        <span class="px-3 py-1 bg-surface-alt text-on-surface-variant border border-border rounded-full text-xs font-medium">Retail</span>
                        <span class="px-3 py-1 bg-surface-alt text-on-surface-variant border border-border rounded-full text-xs font-medium">Services</span>
                    </div>
                </div>

                <div class="flex flex-col gap-2 pt-2">
                    <button type="button" :disabled="submitting" class="w-full py-2.5 bg-white border border-primary text-primary font-semibold text-sm rounded-md hover:bg-primary/5 transition disabled:opacity-50">
                        Apply Filters
                    </button>
                    <button type="submit" :disabled="submitting"
                            class="w-full py-2.5 bg-primary text-white font-semibold text-sm rounded-md shadow hover:bg-primary/90 transition flex items-center justify-center gap-2 disabled:opacity-85 disabled:cursor-not-allowed">
                        <template x-if="!submitting">
                            <span>Summary</span>
                        </template>
                        <template x-if="submitting">
                            <div class="flex items-center gap-2">
                                <svg class="animate-spin size-4 text-white shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span class="truncate" x-text="messages[step]"></span>
                            </div>
                        </template>
                    </button>
                </div>
            </form>
        </div>

    {{-- Floating Market Fit Score (Right) --}}
    @if($latestAnalysis)
        <div class="absolute top-22 right-6 z-10 bg-white/95 backdrop-blur-md w-80 p-5 rounded-xl shadow-lg border border-border flex gap-4 items-center">
                <div class="relative size-16 flex items-center justify-center shrink-0">
                    <svg class="size-full -rotate-90 transform absolute" viewBox="0 0 36 36">
                        <path class="text-surface-alt" stroke-width="3" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="text-primary" stroke-dasharray="{{ $latestAnalysis->market_fit_score }}, 100" stroke-width="3" stroke-linecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="text-center absolute">
                        <span class="block text-xl font-display font-bold text-on-surface leading-none">{{ $latestAnalysis->market_fit_score }}</span>
                        <span class="block text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">Score</span>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-success-bg text-success rounded-sm mb-1">
                        <svg class="size-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span class="text-[10px] font-bold uppercase tracking-wider">Excellent</span>
                    </div>
                    <h3 class="font-bold text-on-surface text-sm">Market Fit</h3>
                    <p class="text-xs text-on-surface-variant leading-tight mt-0.5">High demand detected in this sector.</p>
                </div>
            </div>
    @endif

    @push('scripts')
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.js"></script>
    <script>
        mapboxgl.accessToken = '{{ config("services.mapbox.key") }}';
        
        // Use coordinates from latest analysis, profile, or default to Jakarta
        const centerLng = {{ $latestAnalysis?->longitude ?? ($profile->longitude ?? 106.8272) }};
        const centerLat = {{ $latestAnalysis?->latitude ?? ($profile->latitude ?? -6.1751) }};

        const map = window.map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/light-v11', // Light minimal style like the design
            center: [centerLng, centerLat],
            zoom: 13
        });

        map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'bottom-right');

        // Add a marker for the center
        const el = document.createElement('div');
        el.className = 'w-4 h-4 bg-primary rounded-full shadow-lg border-2 border-white';
        
        const marker = window.marker = new mapboxgl.Marker({ element: el })
            .setLngLat([centerLng, centerLat])
            .addTo(map);
            
        @if($latestAnalysis && $latestAnalysis->competitors)
            // Render competitors on the map if they exist
            @foreach($latestAnalysis->competitors as $comp)
            {
                const compEl = document.createElement('div');
                compEl.className = 'w-3 h-3 bg-on-surface-variant rounded-full opacity-70 border border-white';
                
                new mapboxgl.Marker({ element: compEl })
                    .setLngLat([{{ $comp->longitude }}, {{ $comp->latitude }}])
                    .setPopup(new mapboxgl.Popup({ offset: 10 }).setHTML('<p class="text-xs font-bold">{{ addslashes($comp->name) }}</p>'))
                    .addTo(window.map);
            }
            @endforeach
            
            // Add a radius circle using turf or mapbox source
            window.map.on('load', () => {
                // To keep it simple, we draw a polygon circle around the center
                const radius = {{ $latestAnalysis?->radius_km ?? 2.5 }};
                const points = 64;
                const coords = { latitude: centerLat, longitude: centerLng };
                
                const km = radius;
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
                
                map.addSource('polygon', {
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
                    'id': 'polygon-fill',
                    'type': 'fill',
                    'source': 'polygon',
                    'layout': {},
                    'paint': {
                        'fill-color': '#9D3D2B',
                        'fill-opacity': 0.1
                    }
                });
                
                map.addLayer({
                    'id': 'polygon-stroke',
                    'type': 'line',
                    'source': 'polygon',
                    'layout': {},
                    'paint': {
                        'line-color': '#9D3D2B',
                        'line-width': 1,
                        'line-opacity': 0.3
                    }
                });
            });
        @endif
    </script>
    @endpush
</x-layouts.app>
