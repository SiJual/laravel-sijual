<header class="fixed top-0 right-0 left-0 lg:left-64 h-16 bg-surface border-b border-border z-30 px-6 flex items-center justify-between">
    <div class="flex items-center gap-4 flex-1 max-w-md">
        <div class="relative w-full">
            <input type="text" placeholder="Cari transaksi, riset, atau produk..."
                   class="w-full bg-surface-alt text-on-surface text-sm rounded-full pl-10 pr-4 py-2 border-none focus:ring-2 focus:ring-primary/20 outline-none">
            <svg class="size-4 text-on-surface-variant absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
    </div>
    <div class="flex items-center gap-4">
        <button class="relative p-2 rounded-full hover:bg-surface-alt text-on-surface-variant">
            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span class="absolute top-1.5 right-1.5 size-2 bg-error rounded-full"></span>
        </button>
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" @click.outside="open = false"
                    class="size-9 rounded-full bg-primary-light text-primary font-semibold flex items-center justify-center text-sm uppercase">
                {{ \Illuminate\Support\Str::substr(auth()->user()->full_name ?? auth()->user()->email, 0, 2) }}
            </button>

            <div x-show="open" x-cloak x-transition
                 class="absolute right-0 mt-2 w-56 bg-surface border border-border rounded-lg shadow-lg py-2 z-50">
                <div class="px-4 py-2 border-b border-border">
                    <p class="text-sm font-semibold text-on-surface truncate">{{ auth()->user()->full_name ?? 'Pengguna' }}</p>
                    <p class="text-xs text-on-surface-variant truncate">{{ auth()->user()->email }}</p>
                </div>
                <a href="{{ route('profile.edit') }}"
                   class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-alt">Profil Usaha</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full text-left px-4 py-2 text-sm text-error hover:bg-surface-alt">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
