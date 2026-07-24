<x-layouts.app title="Laporan Riset Pasar — SiPasar" activeNav="sipasar">
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
                <h3 class="text-lg font-bold font-display text-on-surface">Profil Demografi (BPS Open Data)</h3>
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
                        <span class="text-lg font-bold text-on-surface">{{ $analysis->demographic_data['density'] ?? 'Tinggi' }}</span>
                    </div>
                    <div class="p-3 bg-surface-alt rounded border border-border/50">
                        <span class="block text-on-surface-variant font-semibold">Sumber Data</span>
                        <span class="text-lg font-bold text-on-surface uppercase">BPS / OSM</span>
                    </div>
                </div>
            </div>
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
</x-layouts.app>
