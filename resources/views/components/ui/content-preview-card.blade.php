@props([
    'title',
    'type' => 'Social Media',
    'imageUrl' => null,
    'caption' => null,
    'href' => '#',
])

<div class="bg-surface border border-border rounded-lg overflow-hidden shadow-card flex flex-col justify-between hover:border-primary transition">
    @if($imageUrl)
        <div class="h-44 bg-surface-alt overflow-hidden">
            <img src="{{ $imageUrl }}" alt="{{ $title }}" class="w-full h-full object-cover">
        </div>
    @else
        {{-- No poster yet (draft still text-only, or generation skipped the
             image step) — a placeholder so every card keeps the same shape. --}}
        <div class="h-44 bg-gradient-to-br from-primary-subtle via-surface-alt to-primary-subtle flex flex-col items-center justify-center gap-2 text-primary/70">
            <svg class="size-9" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <rect x="3" y="3" width="18" height="18" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="9" cy="9" r="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 15-5-5-9 9"/>
            </svg>
            <span class="text-[11px] font-semibold uppercase tracking-wider">Poster Belum Tersedia</span>
        </div>
    @endif
    <div class="p-4 space-y-2">
        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-primary-subtle text-primary">{{ $type }}</span>
        <h4 class="font-bold text-sm text-on-surface line-clamp-1">{{ $title }}</h4>
        <p class="text-xs text-on-surface-variant line-clamp-2">{{ $caption }}</p>
    </div>
    <div class="p-4 pt-0">
        <a href="{{ $href }}" class="text-xs font-semibold text-primary hover:underline">Lihat Detail →</a>
    </div>
</div>
