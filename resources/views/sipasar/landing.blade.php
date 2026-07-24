<x-layouts.app title="SiPasar — Riset Pasar Geodemografis" activeNav="sipasar">
    <div class="p-6 lg:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold font-display text-on-surface">SiPasar — Riset Pasar Geodemografis</h1>
                <p class="text-sm text-on-surface-variant font-medium">Analisis kompetitor sekitar, kepadatan demografi BPS, dan skor kelayakan lokasi usaha Anda.</p>
            </div>
        </div>

        {{-- Search Form --}}
        <form action="{{ route('sipasar.analyze') }}" method="POST" class="bg-surface p-6 rounded-lg border border-border shadow-card space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label for="location_query" class="block text-xs font-semibold text-on-surface mb-1.5">Lokasi Target Riset</label>
                    <input type="text" id="location_query" name="location_query" value="{{ old('location_query', 'Kebayoran Baru, Jakarta Selatan') }}" required
                           placeholder="Masukkan nama jalan, kecamatan, atau kota..."
                           class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
                <div>
                    <label for="radius_km" class="block text-xs font-semibold text-on-surface mb-1.5">Radius Analisis (KM)</label>
                    <select id="radius_km" name="radius_km" class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 outline-none">
                        <option value="0.5">0.5 KM (Mikro / Lingkungan)</option>
                        <option value="1.0" selected>1.0 KM (Standar)</option>
                        <option value="2.5">2.5 KM (Kecamatan)</option>
                        <option value="5.0">5.0 KM (Kota)</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-6 py-3 bg-primary text-white font-semibold text-sm rounded-md shadow-card hover:bg-primary/90 transition flex items-center gap-2">
                    <span>🔍 Jalankan Analisis Riset Pasar</span>
                </button>
            </div>
        </form>

        {{-- Latest Analysis Summary (if exists) --}}
        @if($latestAnalysis)
            <div class="space-y-4">
                <h3 class="text-lg font-bold font-display text-on-surface">Hasil Analisis Terakhir ({{ $latestAnalysis->location_query }})</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <x-ui.market-fit-score-card
                        :score="$latestAnalysis->market_fit_score"
                        :radius="$latestAnalysis->radius_km"
                        :competitorCount="$latestAnalysis->competitors->count()"
                    />
                    <div class="md:col-span-2 bg-surface p-6 rounded-lg border border-border shadow-card flex flex-col justify-between">
                        <div class="space-y-2">
                            <h4 class="font-bold text-on-surface">Peta Kepadatan & Kompetitor</h4>
                            <p class="text-xs text-on-surface-variant">Ditemukan {{ $latestAnalysis->competitors->count() }} usaha sejenis di sekitar area riset.</p>
                        </div>
                        <div class="pt-4 border-t border-border flex justify-end">
                            <a href="{{ route('sipasar.results', $latestAnalysis->id) }}" class="px-4 py-2 bg-primary text-white font-semibold text-xs rounded shadow hover:bg-primary/90">
                                Lihat Laporan Lengkap →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
