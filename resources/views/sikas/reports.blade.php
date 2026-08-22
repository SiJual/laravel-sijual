<x-layouts.app title="Laporan Keuangan — SiKas" activeNav="sikas" :hideTopBar="true">
    <div class="p-6 lg:p-10 space-y-6 max-w-7xl mx-auto w-full">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl lg:text-4xl font-serif font-bold text-gray-900 tracking-tight">Laporan Keuangan UMKM</h1>
                <p class="text-sm text-gray-500 font-medium mt-1">Rekapitulasi pendapatan, pengeluaran, dan net profit usaha.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('sikas.dashboard') }}" class="text-sm font-semibold text-[#9D3D2B] hover:underline mr-2">
                    ← Dasbor SiKas
                </a>
                {{-- Export PDF --}}
                <form action="{{ route('sikas.reports.export') }}" method="POST" target="_blank">
                    @csrf
                    <input type="hidden" name="format" value="pdf">
                    <input type="hidden" name="period" value="{{ $period }}">
                    <input type="hidden" name="start_date" value="{{ $startDate }}">
                    <input type="hidden" name="end_date" value="{{ $endDate }}">
                    <button type="submit" class="px-4 py-2 bg-error text-white font-semibold text-sm rounded-lg shadow-sm hover:bg-error/90 transition flex items-center gap-1.5">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        <span>Export PDF</span>
                    </button>
                </form>

                {{-- Export CSV --}}
                <form action="{{ route('sikas.reports.export') }}" method="POST">
                    @csrf
                    <input type="hidden" name="format" value="csv">
                    <input type="hidden" name="period" value="{{ $period }}">
                    <input type="hidden" name="start_date" value="{{ $startDate }}">
                    <input type="hidden" name="end_date" value="{{ $endDate }}">
                    <button type="submit" class="px-4 py-2 bg-primary text-white font-semibold text-sm rounded-lg shadow-sm hover:bg-primary/90 transition flex items-center gap-1.5">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        <span>Export CSV / Excel</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Period Tabs & Custom Date Range --}}
        <div class="bg-surface p-4 rounded-xl border border-border space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2 text-sm font-semibold">
                    <a href="?period=daily" class="px-4 py-2 rounded-lg {{ $period === 'daily' ? 'bg-primary text-white' : 'text-on-surface-variant hover:bg-surface-alt' }}">Harian</a>
                    <a href="?period=weekly" class="px-4 py-2 rounded-lg {{ $period === 'weekly' ? 'bg-primary text-white' : 'text-on-surface-variant hover:bg-surface-alt' }}">Mingguan</a>
                    <a href="?period=monthly" class="px-4 py-2 rounded-lg {{ $period === 'monthly' ? 'bg-primary text-white' : 'text-on-surface-variant hover:bg-surface-alt' }}">Bulanan</a>
                </div>

                {{-- Custom Date Form --}}
                <form method="GET" action="{{ route('sikas.reports') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="period" value="custom">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="font-semibold text-on-surface-variant">Rentang:</span>
                        <input type="date" name="start_date" value="{{ $startDate ?? date('Y-m-01') }}"
                               class="px-3 py-1.5 bg-surface-alt border border-border-input rounded-lg text-on-surface font-medium focus:ring-2 focus:ring-primary/20 outline-none">
                        <span class="text-on-surface-variant">s/d</span>
                        <input type="date" name="end_date" value="{{ $endDate ?? date('Y-m-d') }}"
                               class="px-3 py-1.5 bg-surface-alt border border-border-input rounded-lg text-on-surface font-medium focus:ring-2 focus:ring-primary/20 outline-none">
                        <button type="submit" class="px-3.5 py-1.5 bg-primary text-white font-semibold rounded-lg shadow-sm hover:bg-primary/90 transition">
                            Terapkan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Aggregates Bento Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-ui.stat-card
                title="Total Pemasukan"
                value="Rp {{ number_format($reportData['total_income'], 0, ',', '.') }}"
                icon='<svg class="size-6 text-success" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>'
            />
            <x-ui.stat-card
                title="Total Pengeluaran"
                value="Rp {{ number_format($reportData['total_expense'], 0, ',', '.') }}"
                icon='<svg class="size-6 text-error" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/></svg>'
            />
            <x-ui.stat-card
                title="Keuntungan Bersih (Net)"
                value="Rp {{ number_format($reportData['net_profit'], 0, ',', '.') }}"
                icon='<svg class="size-6 text-primary" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/></svg>'
                trend="{{ $reportData['net_profit'] >= 0 ? 'Surplus' : 'Defisit' }}"
            />
        </div>

        {{-- Breakdown by Category --}}
        <div class="bg-surface border border-border rounded-lg p-6 shadow-card space-y-4">
            <h3 class="text-lg font-bold font-display text-on-surface">Rincian Per Kategori</h3>
            @if(empty($reportData['category_breakdown']))
                <div class="p-8 text-center text-on-surface-variant text-sm font-medium">Belum ada transaksi untuk periode ini.</div>
            @else
                <div class="divide-y divide-border/40">
                    @foreach($reportData['category_breakdown'] as $category => $amount)
                        <div class="py-3 flex items-center justify-between text-sm">
                            <span class="font-semibold text-on-surface">{{ $category }}</span>
                            <span class="font-bold text-on-surface">Rp {{ number_format($amount, 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
