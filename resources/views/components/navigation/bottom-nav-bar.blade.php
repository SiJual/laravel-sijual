@props(['active' => ''])

<nav {{ $attributes->merge(['class' => 'fixed bottom-0 left-0 right-0 h-16 bg-surface border-t border-border flex items-center justify-around z-40 lg:hidden']) }}>
    <a href="{{ route('dashboard') }}" @class(['flex flex-col items-center gap-1 text-xs font-semibold', 'text-primary' => $active === 'hub', 'text-on-surface-variant' => $active !== 'hub'])>
        <span>Hub</span>
    </a>
    <a href="{{ route('sikas.dashboard') }}" @class(['flex flex-col items-center gap-1 text-xs font-semibold', 'text-primary' => $active === 'sikas', 'text-on-surface-variant' => $active !== 'sikas'])>
        <span>SiKas</span>
    </a>
    <a href="{{ route('sipasar.landing') }}" @class(['flex flex-col items-center gap-1 text-xs font-semibold', 'text-primary' => $active === 'sipasar', 'text-on-surface-variant' => $active !== 'sipasar'])>
        <span>SiPasar</span>
    </a>
    <a href="{{ route('sipromo.landing') }}" @class(['flex flex-col items-center gap-1 text-xs font-semibold', 'text-primary' => $active === 'sipromo', 'text-on-surface-variant' => $active !== 'sipromo'])>
        <span>SiPromo</span>
    </a>
</nav>
