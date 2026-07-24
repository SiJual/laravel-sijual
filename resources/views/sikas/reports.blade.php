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
                        <span>📥 Export CSV / Excel</span>
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
                icon="📥"
            />
            <x-ui.stat-card
                title="Total Pengeluaran"
                value="Rp {{ number_format($reportData['total_expense'], 0, ',', '.') }}"
                icon="📤"
            />
            <x-ui.stat-card
                title="Keuntungan Bersih (Net)"
                value="Rp {{ number_format($reportData['net_profit'], 0, ',', '.') }}"
                icon="📊"
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
