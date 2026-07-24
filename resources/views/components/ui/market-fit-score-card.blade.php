@props([
    'score' => 85,
    'radius' => 1.0,
    'competitorCount' => 3,
    'density' => 'Tinggi',
])

<div class="bg-surface border border-border rounded-lg p-6 shadow-card space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold text-on-surface-variant tracking-wide uppercase">Market Fit Score</p>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-4xl font-bold font-display text-primary">{{ $score }}</span>
                <span class="text-sm font-semibold text-on-surface-variant">/ 100</span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $score >= 80 ? 'bg-success-bg text-success' : 'bg-hero-accent text-primary' }}">
                    {{ $score >= 80 ? 'Sangat Baik' : 'Potensial' }}
                </span>
            </div>
        </div>
        <div class="size-14 rounded-full border-4 border-primary/20 flex items-center justify-center text-primary font-bold text-lg font-display">
            {{ $score }}%
        </div>
    </div>

    <div class="grid grid-cols-3 gap-2 pt-4 border-t border-border/50 text-center text-xs">
        <div class="p-2 rounded bg-surface-alt">
            <span class="block text-on-surface-variant font-medium">Radius</span>
            <span class="font-bold text-on-surface">{{ $radius }} km</span>
        </div>
        <div class="p-2 rounded bg-surface-alt">
            <span class="block text-on-surface-variant font-medium">Kompetitor</span>
            <span class="font-bold text-on-surface">{{ $competitorCount }} Usaha</span>
        </div>
        <div class="p-2 rounded bg-surface-alt">
            <span class="block text-on-surface-variant font-medium">Kepadatan</span>
            <span class="font-bold text-on-surface">{{ $density }}</span>
        </div>
    </div>
</div>
