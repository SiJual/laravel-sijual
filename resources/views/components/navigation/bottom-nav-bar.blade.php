@props(['active' => ''])

<nav {{ $attributes->merge(['class' => 'fixed bottom-0 left-0 right-0 h-16 bg-surface border-t border-border flex items-center justify-around z-40 lg:hidden']) }}>
    <a href="#" class="flex flex-col items-center gap-1 text-xs font-semibold text-on-surface-variant">
        <span>Hub</span>
    </a>
    <a href="#" class="flex flex-col items-center gap-1 text-xs font-semibold text-on-surface-variant">
        <span>SiKas</span>
    </a>
    <a href="#" class="flex flex-col items-center gap-1 text-xs font-semibold text-on-surface-variant">
        <span>SiPasar</span>
    </a>
    <a href="#" class="flex flex-col items-center gap-1 text-xs font-semibold text-on-surface-variant">
        <span>SiPromo</span>
    </a>
</nav>
