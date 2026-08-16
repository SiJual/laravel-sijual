<x-layouts.app title="SiPasar — Riwayat Riset Pasar" activeNav="sipasar">
    <div class="p-6 lg:p-8 space-y-6 max-w-5xl mx-auto">
        
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-serif font-bold text-gray-900">Riwayat Riset Pasar</h1>
                <p class="text-sm text-gray-500 mt-1">Bandingkan dan akses ulang seluruh analisis lokasi yang pernah kamu jalankan.</p>
            </div>
            <a href="{{ route('sipasar.landing') }}"
               class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg shadow hover:bg-primary/90 transition">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12l7-7 7 7"/></svg>
                Analisis Baru
            </a>
        </div>

        {{-- Analysis List --}}
        @if($analyses->isEmpty())
            <div class="bg-white border border-[#F2E8E5] rounded-2xl p-12 text-center">
                <div class="mx-auto size-16 rounded-full bg-[#FAF4F2] flex items-center justify-center mb-4">
                    <svg class="size-8 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/></svg>
                </div>
                <h3 class="text-base font-bold text-gray-700 mb-2">Belum Ada Analisis</h3>
                <p class="text-sm text-gray-500">Jalankan riset pasar pertamamu untuk mulai memahami peluang di area usahamu.</p>
                <a href="{{ route('sipasar.landing') }}" class="inline-block mt-4 px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary/90 transition">
                    Mulai Riset Sekarang
                </a>
            </div>
        @else
            <div class="space-y-4">
                @foreach($analyses as $analysis)
                    <div class="bg-white border border-[#F2E8E5] rounded-2xl p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 hover:shadow-md transition-shadow">
                        <div class="flex items-start gap-4">
                            {{-- Score Badge --}}
                            <div class="shrink-0 relative size-14 flex items-center justify-center">
                                <svg class="size-full -rotate-90 transform absolute" viewBox="0 0 36 36">
                                    <path class="text-gray-100" stroke-width="3" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                    <path class="{{ $analysis->market_fit_score >= 75 ? 'text-green-500' : ($analysis->market_fit_score >= 50 ? 'text-yellow-500' : 'text-red-500') }}"
                                          stroke-dasharray="{{ $analysis->market_fit_score }}, 100"
                                          stroke-width="3" stroke-linecap="round" stroke="currentColor" fill="none"
                                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                </svg>
                                <div class="text-center absolute">
                                    <span class="text-sm font-bold text-gray-900 leading-none">{{ $analysis->market_fit_score }}</span>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-base font-bold text-gray-900">{{ $analysis->location_query }}</h3>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                                    <span class="text-xs text-gray-500">
                                        <svg class="size-3 inline-block mr-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                                        Radius {{ $analysis->radius_km }} km
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        <svg class="size-3 inline-block mr-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                        {{ $analysis->competitors_count }} kompetitor
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        <svg class="size-3 inline-block mr-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
                                        {{ $analysis->created_at->locale('id')->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0">
                            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $analysis->market_fit_score >= 75 ? 'bg-green-50 text-green-700' : ($analysis->market_fit_score >= 50 ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700') }}">
                                {{ $analysis->market_fit_score >= 75 ? 'Excellent' : ($analysis->market_fit_score >= 50 ? 'Fair' : 'Poor') }}
                            </span>
                            <a href="{{ route('sipasar.results', $analysis->id) }}"
                               class="px-4 py-2 bg-[#FAF4F2] border border-[#F2E8E5] text-[#9D3D2B] text-sm font-semibold rounded-lg hover:bg-[#9D3D2B] hover:text-white transition-colors">
                                Lihat Laporan
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="pt-4">
                {{ $analyses->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>
