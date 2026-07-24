@props([
    'label',
    'active' => false,
])

<button type="button" {{ $attributes->merge(['class' => 'px-3 py-1.5 rounded-full text-xs font-semibold border transition flex items-center gap-1.5 ' . ($active ? 'bg-primary text-white border-primary' : 'bg-surface-alt text-on-surface-variant border-border-input hover:bg-surface-warm')]) }}>
    <span>{{ $label }}</span>
    <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
    </svg>
</button>
