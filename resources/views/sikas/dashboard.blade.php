<x-layouts.app title="SiKas — Keuangan Pintar" activeNav="sikas">
    <div x-data="{ showModal: false, txType: 'income' }" class="p-6 lg:p-8 space-y-6">
        {{-- Header & Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold font-display text-on-surface">Dasbor Keuangan SiKas</h1>
                <p class="text-sm text-on-surface-variant font-medium">Input suara, kategorisasi AI, dan analisis saldo usaha.</p>
            </div>
            <div class="flex items-center gap-3">
                <button @click="showModal = true; txType = 'income'" type="button" class="px-4 py-2 bg-success text-white font-semibold text-sm rounded-md shadow-sm hover:bg-success/90 transition">
                    + Pemasukan
                </button>
                <button @click="showModal = true; txType = 'expense'" type="button" class="px-4 py-2 bg-primary text-white font-semibold text-sm rounded-md shadow-sm hover:bg-primary/90 transition">
                    + Pengeluaran
                </button>
            </div>
        </div>

        {{-- Status Flash Alerts --}}
        @if(session('success'))
            <div class="p-4 rounded-md bg-success-bg text-success text-sm font-semibold">
                {{ session('success') }}
            </div>
        @endif

        {{-- Voice Input Bar --}}
        <x-ui.voice-input-bar />

        {{-- Summary Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-ui.stat-card
                title="Total Saldo Bersih"
                value="Rp {{ number_format($totalBalance, 0, ',', '.') }}"
                icon="💵"
                trend="Saldo Real-time"
            />
            <x-ui.stat-card
                title="Total Pemasukan"
                value="Rp {{ number_format($totalIncome, 0, ',', '.') }}"
                icon="📥"
                trend="↑ Pemasukan bulan ini"
            />
            <x-ui.stat-card
                title="Total Pengeluaran"
                value="Rp {{ number_format($totalExpense, 0, ',', '.') }}"
                icon="📤"
                trendLabel="Pengeluaran tercatat"
            />
        </div>

        {{-- AI Insights Recommendation Card --}}
        <div class="p-6 rounded-lg bg-surface border border-border shadow-card space-y-3">
            <div class="flex items-center gap-2 text-primary font-bold text-base font-display">
                <span>🤖 Rekomendasi AI SiKas</span>
            </div>
            <p class="text-sm text-on-surface-variant leading-relaxed font-medium">
                {{ $aiInsight }}
            </p>
        </div>

        {{-- Transaction History Table / Quick List --}}
        <div class="bg-surface border border-border rounded-lg p-6 shadow-card space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold font-display text-on-surface">Riwayat Transaksi Terakhir</h3>
                <a href="{{ route('sikas.transactions.index') }}" class="text-xs font-semibold text-primary hover:underline">Kelola Semua Transaksi →</a>
            </div>

            @if($transactions->isEmpty())
                <div class="p-8 text-center text-on-surface-variant/70 text-sm font-medium">
                    Belum ada transaksi. Tambahkan pemasukan atau pengeluaran pertama Anda!
                </div>
            @else
                <div class="space-y-3">
                    @foreach($transactions as $tx)
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

        {{-- Manual Transaction Modal --}}
        <div x-show="showModal"
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/50 backdrop-blur-sm"
             style="display: none;">
            <div @click.away="showModal = false" class="bg-surface rounded-xl border border-border p-6 w-full max-w-md shadow-hero space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <h3 class="text-lg font-bold font-display text-on-surface" x-text="txType === 'income' ? 'Tambah Pemasukan' : 'Tambah Pengeluaran'"></h3>
                    <button @click="showModal = false" type="button" class="text-on-surface-variant hover:text-on-surface text-lg font-bold">&times;</button>
                </div>

                <form action="{{ route('sikas.transactions.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="type" :value="txType">

                    <x-form.input label="Deskripsi / Nama Transaksi" name="description" placeholder="Contoh: Penjualan 10 cangkir kopi" required />

                    <x-form.input label="Nominal (Rp)" name="amount" type="number" placeholder="50000" required />

                    <x-form.select label="Kategori" name="category_id">
                        <option value="">-- Pilih Kategori --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->type === 'income' ? 'Masuk' : 'Keluar' }})</option>
                        @endforeach
                    </x-form.select>

                    <x-form.input label="Tanggal Transaksi" name="transaction_date" type="date" value="{{ date('Y-m-d') }}" required />

                    <div class="flex justify-end gap-3 pt-4 border-t border-border">
                        <x-form.button type="button" variant="secondary" @click="showModal = false">Batal</x-form.button>
                        <x-form.button type="submit" variant="primary">Simpan Transaksi</x-form.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
