@props(['active' => ''])

<aside class="fixed left-0 top-0 bottom-0 w-64 bg-surface border-r border-border shadow-sm flex flex-col justify-between py-6 z-40 hidden lg:flex">
    <div>
        {{-- Logo --}}
        <div class="flex items-center gap-2 px-4 pb-8">
            <div class="size-10 bg-primary rounded-full flex items-center justify-center">
                <svg class="size-5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold font-display text-primary">SiJual</h1>
                <p class="text-xs font-semibold text-on-surface-variant tracking-wide">MSME Command Center</p>
            </div>
        </div>

        {{-- New Transaction CTA --}}
        <div class="px-2 pb-2">
            <a href="{{ route('sikas.dashboard') }}"
               class="flex items-center justify-center gap-2 w-full bg-primary text-white font-semibold text-sm py-2 px-4 rounded-md shadow-sm hover:bg-primary/90 transition">
                <svg class="size-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>
                New Transaction
            </a>
        </div>

        {{-- Nav Links --}}
        <nav class="flex flex-col gap-1 mt-2">
            <x-navigation.nav-link href="{{ route('dashboard') }}" :active="$active === 'hub'" icon="grid">
                SiJual Hub
            </x-navigation.nav-link>
            <x-navigation.nav-link href="{{ route('sikas.dashboard') }}" :active="$active === 'sikas'" icon="wallet">
                SiKas
            </x-navigation.nav-link>
            <x-navigation.nav-link href="{{ route('sipasar.landing') }}" :active="$active === 'sipasar'" icon="map">
                SiPasar
            </x-navigation.nav-link>
            <x-navigation.nav-link href="{{ route('sipromo.landing') }}" :active="$active === 'sipromo'" icon="megaphone">
                SiPromo
            </x-navigation.nav-link>
            <x-navigation.nav-link href="{{ route('sistok.products.index') }}" :active="$active === 'sistok'" icon="box">
                SiStok
            </x-navigation.nav-link>
        </nav>
    </div>

    {{-- Bottom Links --}}
    <div class="flex flex-col gap-1 px-2">
        <x-navigation.nav-link href="{{ route('profile.edit') }}" icon="settings">Settings</x-navigation.nav-link>
        <x-navigation.nav-link href="mailto:support@sijual.id" icon="help">Support</x-navigation.nav-link>
    </div>
</aside>
