@props([
    'variant' => 'primary', // 'primary' | 'secondary' | 'outline' | 'danger'
    'type' => 'submit',
])

@php
$classes = match($variant) {
    'secondary' => 'bg-surface-alt hover:bg-surface-warm text-on-surface border border-border-input',
    'outline' => 'bg-transparent border border-primary text-primary hover:bg-primary-subtle',
    'danger' => 'bg-error hover:bg-error/90 text-white shadow-sm',
    default => 'bg-primary hover:bg-primary/90 text-white shadow-card',
};
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => "py-3 px-4 font-semibold text-sm rounded-md transition duration-150 flex items-center justify-center gap-2 {$classes}"]) }}>
    {{ $slot }}
</button>
