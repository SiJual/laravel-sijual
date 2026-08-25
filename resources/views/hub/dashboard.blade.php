<x-layouts.app title="SiJual Hub — Command Center" activeNav="hub">
    <div class="p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        {{-- Welcome Header --}}
        <div class="bg-[#FCF7F6] p-8 rounded-2xl border border-[#F2E8E5] flex flex-col sm:flex-row sm:items-start justify-between gap-4 relative overflow-hidden">
            <div class="relative z-10">
                <h1 class="text-3xl font-serif font-bold text-gray-900 tracking-tight">
                    Halo, {{ $profile->business_name }}
                </h1>
                <p class="text-sm text-gray-600 font-medium mt-2">
                    Ringkasan bisnis kamu hari ini.
                </p>
            </div>
            <div class="relative z-10">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-gray-200 bg-gray-50/50 text-xs font-semibold text-gray-700 shadow-sm backdrop-blur-sm">
                    <svg class="size-3.5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    {{ now()->translatedFormat('d F Y') }}
                </div>
            </div>
            
            {{-- Decorative background element --}}
            <div class="absolute top-0 left-0 p-4 opacity-30 pointer-events-none">
                <div class="size-16 rounded-full border-[12px] border-white/50"></div>
            </div>
        </div>

        {{-- Stat Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Card 1: Penjualan --}}
            <div class="bg-white border border-[#F2E8E5] rounded-[20px] p-6 shadow-sm relative">
                <div class="absolute right-6 top-6 size-10 rounded-full bg-[#FCF0ED] text-[#9D3D2B] flex items-center justify-center">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                </div>
                <p class="text-sm font-semibold text-gray-600">Penjualan Hari Ini</p>
                <h3 class="text-[28px] font-serif font-bold text-gray-900 mt-2">@rupiahShort($todayRevenue)</h3>
                @if(! is_null($revenueChange))
                    <div class="flex items-center gap-1.5 mt-8 text-sm font-semibold {{ $revenueChange >= 0 ? 'text-[#4E8057]' : 'text-[#9D3D2B]' }}">
                        @if($revenueChange >= 0)
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m5 12 7-7 7 7"/><path d="M12 19V5"/></svg>
                        @else
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m19 12-7 7-7-7"/><path d="M12 5v14"/></svg>
                        @endif
                        {{ abs($revenueChange) }}% vs kemarin
                    </div>
                @else
                    <p class="mt-8 text-sm text-gray-400 font-medium">Belum ada pembanding kemarin</p>
                @endif
            </div>

            {{-- Card 2: Market Score --}}
            <div class="bg-white border border-[#F2E8E5] rounded-[20px] p-6 shadow-sm relative">
                <div class="absolute right-6 top-6 size-10 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/><path d="M22 7v3a2 2 0 0 1-2 2v0a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12v0a2 2 0 0 1-2-2V7"/></svg>
                </div>
                <p class="text-sm font-semibold text-gray-600">Market Score</p>
                <h3 class="text-[28px] font-serif font-bold text-gray-900 mt-2">{{ is_null($marketScore) ? '—' : $marketScore . '/100' }}</h3>
                <div class="mt-8">
                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                        <div class="bg-[#9D3D2B] h-1.5 rounded-full" style="width: {{ $marketScore ?? 0 }}%"></div>
                    </div>
                    @if(is_null($marketScore))
                        <p class="text-[11px] text-right mt-2 text-gray-400 font-medium">Jalankan analisis di SiPasar</p>
                    @endif
                </div>
            </div>

            {{-- Card 3: Campaigns --}}
            <div class="bg-gradient-to-br from-[#EAD6D1] via-[#FDF9F8] to-[#D5CCC9] border border-[#EAD6D1]/50 rounded-[20px] p-6 shadow-sm relative overflow-hidden">
                <div class="absolute right-6 top-6 size-10 rounded-full bg-[#E8D4CF] text-[#9D3D2B] flex items-center justify-center shadow-[inset_0_1px_2px_rgba(255,255,255,0.5)]">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                </div>
                <p class="text-sm font-semibold text-[#5A4A48]">Active Campaigns</p>
                <h3 class="text-[28px] font-serif font-bold text-gray-900 mt-2">{{ $activeCampaigns }} Promos</h3>

                @if($latestCampaign)
                    <div class="mt-8 bg-white/70 backdrop-blur-sm rounded-md p-1.5 flex justify-between items-center text-[12px] font-bold shadow-sm">
                        <span class="pl-2 text-gray-800 truncate">{{ $latestCampaign->title ?? 'Konten promosi' }}</span>
                        <span class="px-2 py-0.5 text-[#4E8057] shrink-0">Active</span>
                    </div>
                @else
                    <a href="{{ route('sipromo.landing') }}" class="mt-8 block bg-white/70 backdrop-blur-sm rounded-md p-2 text-center text-[12px] font-bold text-[#9D3D2B] shadow-sm hover:bg-white">
                        Buat promo pertama
                    </a>
                @endif
            </div>
        </div>

        {{-- Bottom Section --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pt-2">
            
            {{-- Recent Transactions --}}
            <div class="lg:col-span-2 bg-white border border-[#F2E8E5] rounded-[20px] p-7 shadow-sm">
                <div class="flex items-center justify-between border-b border-[#F2E8E5] pb-5 mb-2">
                    <h2 class="text-xl font-serif font-bold text-gray-900">Recent Transactions</h2>
                    <a href="{{ route('sikas.dashboard') }}" class="text-sm font-bold text-[#9D3D2B] hover:underline">View All</a>
                </div>

                @if($recentTransactions->isEmpty())
                    <div class="py-14 text-center">
                        <div class="mx-auto size-12 rounded-full bg-[#FCF0ED] text-[#9D3D2B] flex items-center justify-center">
                            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
                        </div>
                        <p class="mt-4 text-sm font-bold text-gray-900">Belum ada transaksi</p>
                        <p class="mt-1 text-[13px] text-gray-500">Catat transaksi pertama kamu lewat SiKas.</p>
                        <a href="{{ route('sikas.dashboard') }}" class="inline-block mt-5 px-4 py-2 rounded-lg bg-[#9D3D2B] text-white text-[13px] font-bold hover:bg-[#873425]">
                            Catat transaksi
                        </a>
                    </div>
                @else
                    <div class="divide-y divide-[#F2E8E5]">
                        @foreach($recentTransactions as $tx)
                            <div class="flex items-center justify-between py-5">
                                <div class="flex items-center gap-4">
                                    <div class="size-11 rounded-full flex items-center justify-center shrink-0 {{ $tx->type === 'income' ? 'bg-[#FCF0ED] text-[#9D3D2B]' : 'bg-gray-100 text-gray-500' }}">
                                        @if($tx->type === 'income')
                                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                                        @else
                                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-gray-900">{{ $tx->description ?? 'Transaksi' }}</p>
                                        <p class="text-[13px] text-gray-500 mt-0.5">{{ $tx->transaction_date->format('M d, h:i A') }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-bold {{ $tx->type === 'income' ? 'text-[#4E8057]' : 'text-gray-900' }}">
                                        {{ $tx->type === 'income' ? '+' : '-' }} @rupiahShort($tx->amount)
                                    </p>
                                    <span class="inline-block mt-1 px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] uppercase font-bold rounded">
                                        {{ $tx->type === 'income' ? 'Income' : 'Expense' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Market Alerts --}}
            <div>
                <h2 class="text-xl font-serif font-bold text-gray-900 mb-5">Market Alerts</h2>

                @if($latestAnalysis)
                    @php
                        $meta = $latestAnalysis->analysis_data ?? [];
                        $demo = $latestAnalysis->demographic_data ?? [];
                    @endphp
                    <div class="space-y-4">
                        <div class="bg-white border border-[#F2E8E5] rounded-[16px] p-5 shadow-sm">
                            <div class="flex items-start gap-3">
                                <div class="text-[#9D3D2B] mt-0.5">
                                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                                </div>
                                <div>
                                    <h4 class="text-[15px] font-bold text-gray-900">Potensi Pasar {{ ucfirst($meta['market_potential_label'] ?? 'terukur') }}</h4>
                                    <p class="text-[13.5px] text-gray-600 mt-1.5 leading-relaxed">
                                        {{ $latestAnalysis->location_query }} — skor {{ $latestAnalysis->market_fit_score }}/100,
                                        persaingan {{ $meta['competition_level'] ?? 'belum terukur' }}.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white border border-[#F2E8E5] rounded-[16px] p-5 shadow-sm">
                            <div class="flex items-start gap-3">
                                <div class="text-gray-600 mt-0.5">
                                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                                </div>
                                <div>
                                    <h4 class="text-[15px] font-bold text-gray-900">Profil Konsumen</h4>
                                    <p class="text-[13.5px] text-gray-600 mt-1.5 leading-relaxed">
                                        Populasi {{ number_format($demo['population'] ?? 0, 0, ',', '.') }} jiwa,
                                        daya beli {{ $demo['economic_indicator'] ?? 'menengah' }},
                                        kepadatan {{ $demo['density'] ?? 'sedang' }}.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <a href="{{ route('sipasar.results', $latestAnalysis->id) }}" class="block text-center text-[13px] font-bold text-[#9D3D2B] hover:underline">
                            Lihat analisis lengkap
                        </a>
                    </div>
                @else
                    <div class="bg-white border border-[#F2E8E5] rounded-[16px] p-5 shadow-sm">
                        <p class="text-[13.5px] text-gray-600 leading-relaxed">
                            Belum ada sinyal pasar. Jalankan analisis lokasi di SiPasar untuk melihat kompetitor dan potensi pasar di sekitar usahamu.
                        </p>
                        <a href="{{ route('sipasar.landing') }}" class="inline-block mt-4 text-[13px] font-bold text-[#9D3D2B] hover:underline">
                            Buka SiPasar
                        </a>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-layouts.app>
