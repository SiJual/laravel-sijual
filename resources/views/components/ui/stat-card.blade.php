@props([
    'title',
    'value',
    'icon' => null,
    'trend' => null,
    'trendLabel' => null,
    'href' => null,
    'linkText' => 'Lihat Detail →',
])

<div class="bg-surface border border-border rounded-lg p-6 shadow-card flex flex-col justify-between h-48">
    <div class="flex items-start justify-between">
        <div class="space-y-1">
            <p class="text-xs font-semibold text-on-surface-variant tracking-wide uppercase">{{ $title }}</p>
            <h3 class="text-2xl font-bold font-display text-on-surface">{{ $value }}</h3>
        </div>
        @if($icon)
            <div class="size-10 rounded-full bg-primary-light text-primary flex items-center justify-center">
                {!! $icon !!}
            </div>
        @endif
    </div>
    <div class="flex items-center justify-between mt-auto pt-4 border-t border-border/50 text-xs">
        @if($trend)
            <span class="font-semibold text-success">{{ $trend }}</span>
        @elseif($trendLabel)
            <span class="font-semibold text-on-surface-variant">{{ $trendLabel }}</span>
        @else
            <span></span>
        @endif

        @if($href)
            <a href="{{ $href }}" class="text-primary font-semibold hover:underline">{{ $linkText }}</a>
        @endif
    </div>
</div>
