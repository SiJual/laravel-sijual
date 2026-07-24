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
