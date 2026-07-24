<x-layouts.app title="SiJual Hub — Command Center" activeNav="hub">
    <div class="p-6 lg:p-8 space-y-6">
        {{-- Welcome Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-surface p-6 rounded-lg border border-border shadow-card">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold font-display text-on-surface">
                        Halo, {{ $profile->business_name ?? 'Pemilik UMKM' }}
                    </h1>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-success-bg text-success text-xs font-semibold">
                        <span class="size-2 rounded-full bg-success"></span>
                        Bisnis Sehat
                    </span>
                </div>
                <p class="text-sm text-on-surface-variant font-medium mt-1">
                    Command Center — Ringkasan performa usaha Anda hari ini.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('sikas.dashboard') }}" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-md shadow-sm hover:bg-primary/90 transition">
                    + Catat Transaksi
                </a>
            </div>
        </div>

        {{-- Bento Grid Stat Cards (Using Reusable <x-ui.stat-card>) --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- SiKas Stat Card --}}
            <x-ui.stat-card
                title="SiKas — Total Pendapatan"
                value="Rp {{ number_format($totalRevenue, 0, ',', '.') }}"
                icon="💰"
                trend="↑ Real-time sync"
                href="{{ route('sikas.dashboard') }}"
                linkText="Ke SiKas →"
            />

            {{-- SiPasar Stat Card --}}
            <x-ui.stat-card
                title="SiPasar — Market Fit Score"
                value="{{ $marketScore }} / 100"
                icon="🗺️"
                trendLabel="Radius 1.0 km"
                href="{{ route('sipasar.landing') }}"
                linkText="Ke SiPasar →"
            />

            {{-- SiPromo Stat Card --}}
            <x-ui.stat-card
                title="SiPromo — Kampanye Aktif"
                value="{{ $activeCampaigns }} Konten"
                icon="🎨"
                trendLabel="AI Generated"
                href="{{ route('sipromo.landing') }}"
                linkText="Ke SiPromo →"
            />
        </div>

        {{-- Activity Feed --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Recent Transactions (2/3) (Using Reusable <x-ui.transaction-list-item>) --}}
            <div class="lg:col-span-2 bg-surface border border-border rounded-lg p-6 shadow-card space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold font-display text-on-surface">Transaksi Terakhir</h3>
                    <a href="{{ route('sikas.dashboard') }}" class="text-xs font-semibold text-primary hover:underline">Lihat Semua</a>
                </div>

                @if($recentTransactions->isEmpty())
                    <div class="p-8 text-center text-on-surface-variant/70 text-sm font-medium">
                        Belum ada transaksi. Gunakan pencatatan suara atau manual di SiKas!
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($recentTransactions as $tx)
                            <x-ui.transaction-list-item
                                :description="$tx->description ?? 'Transaksi'"
                                :category="$tx->category->name ?? 'Umum'"
                                :date="$tx->transaction_date->format('d M Y')"
                                :amount="$tx->amount"
                                :type="$tx->type"
                            />
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Market Alerts (1/3) (Using Reusable <x-ui.market-alert-card>) --}}
            <div class="bg-surface border border-border rounded-lg p-6 shadow-card space-y-4">
                <h3 class="text-lg font-bold font-display text-on-surface">Rekomendasi AI</h3>
                <x-ui.market-alert-card
                    badge="Demand Spike"
                    icon="🔥"
                    description="Permintaan kuliner kopi meningkat 18% di wilayah Kebayoran. Pertimbangkan buat promosi khusus jam makan siang via SiPromo!"
                />
            </div>
        </div>
    </div>
</x-layouts.app>
