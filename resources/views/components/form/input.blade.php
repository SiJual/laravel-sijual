@props([
    'disabled' => false,
    'label' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
])

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-xs font-semibold text-on-surface mb-1.5">{{ $label }}</label>
    @endif
    <input {{ $disabled ? 'disabled' : '' }}
           type="{{ $type }}"
           name="{{ $name }}"
           id="{{ $name }}"
           {{ $attributes->merge(['class' => 'w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition disabled:opacity-50']) }}>
    @if($error)
        <p class="mt-1 text-xs font-semibold text-error">{{ $error }}</p>
    @endif
</div>
