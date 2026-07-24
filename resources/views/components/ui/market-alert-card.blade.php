@props([
    'title',
    'description',
    'icon' => '🔥',
    'badge' => 'Demand Spike',
])

<div class="p-4 rounded-md bg-primary-subtle border border-primary/20 space-y-2">
    <div class="flex items-center gap-2 text-primary font-semibold text-sm">
        <span>{{ $icon }} {{ $badge }}</span>
    </div>
    <p class="text-xs text-on-surface-variant leading-relaxed">
        {{ $description }}
    </p>
</div>
