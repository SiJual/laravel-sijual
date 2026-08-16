<x-layouts.app title="Laporan Keuangan — SiKas" activeNav="sikas">
    <div class="p-6 lg:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold font-display text-on-surface">Laporan Keuangan UMKM</h1>
                <p class="text-sm text-on-surface-variant font-medium">Rekapitulasi pendapatan, pengeluaran, dan net profit usaha.</p>
            </div>
            <div class="flex items-center gap-3">
                <form action="{{ route('sikas.reports.export') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-primary text-white font-semibold text-sm rounded-md shadow-sm hover:bg-primary/90 transition flex items-center gap-2">
                        <span><svg class="size-4 inline-block mr-1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg> Export CSV / Excel</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Period Tabs --}}
        <div class="flex items-center gap-2 border-b border-border pb-3 text-sm font-semibold">
            <a href="?period=daily" class="px-4 py-2 rounded-md {{ $period === 'daily' ? 'bg-primary text-white' : 'text-on-surface-variant hover:bg-surface-alt' }}">Harian</a>
            <a href="?period=weekly" class="px-4 py-2 rounded-md {{ $period === 'weekly' ? 'bg-primary text-white' : 'text-on-surface-variant hover:bg-surface-alt' }}">Mingguan</a>
            <a href="?period=monthly" class="px-4 py-2 rounded-md {{ $period === 'monthly' ? 'bg-primary text-white' : 'text-on-surface-variant hover:bg-surface-alt' }}">Bulanan</a>
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
