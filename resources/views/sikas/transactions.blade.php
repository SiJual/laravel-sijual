<x-layouts.app title="Riwayat Transaksi — SiKas" activeNav="sikas">
    <div x-data="{ selectedTx: null }" class="p-6 lg:p-8 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold font-display text-on-surface">Riwayat Transaksi</h1>
                <p class="text-sm text-on-surface-variant font-medium">Filter, cari, dan tinjau detail seluruh catatan keuangan Anda.</p>
            </div>
            <a href="{{ route('sikas.dashboard') }}" class="text-sm font-semibold text-primary hover:underline">← Kembali ke Dasbor SiKas</a>
        </div>

        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('sikas.transactions.index') }}" class="flex flex-wrap items-center gap-3 p-4 bg-surface rounded-lg border border-border shadow-sm">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari transaksi..."
                   class="px-3.5 py-2 bg-surface-alt border border-border-input rounded-full text-xs text-on-surface focus:ring-2 focus:ring-primary/20 outline-none w-48 sm:w-64">

            <select name="type" onchange="this.form.submit()" class="px-3 py-2 bg-surface-alt border border-border-input rounded-full text-xs text-on-surface focus:ring-2 focus:ring-primary/20 outline-none">
                <option value="">Semua Jenis</option>
                <option value="income" {{ request('type') === 'income' ? 'selected' : '' }}>Pemasukan (+)</option>
                <option value="expense" {{ request('type') === 'expense' ? 'selected' : '' }}>Pengeluaran (-)</option>
            </select>

            <select name="category_id" onchange="this.form.submit()" class="px-3 py-2 bg-surface-alt border border-border-input rounded-full text-xs text-on-surface focus:ring-2 focus:ring-primary/20 outline-none">
                <option value="">Semua Kategori</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') === $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="px-4 py-2 bg-primary text-white font-semibold text-xs rounded-full shadow-sm">Filter</button>
            @if(request()->hasAny(['search', 'type', 'category_id']))
                <a href="{{ route('sikas.transactions.index') }}" class="text-xs text-error font-semibold hover:underline">Reset</a>
            @endif
        </form>

        {{-- Transactions List & Detail Panel --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-3">
                @if($transactions->isEmpty())
                    <div class="p-8 bg-surface rounded-lg border border-border text-center text-on-surface-variant text-sm font-medium">
                        Tidak ada transaksi yang cocok dengan kriteria pencarian.
                    </div>
                @else
                    @foreach($transactions as $tx)
                        <div @click="selectedTx = {{ json_encode($tx) }}" class="cursor-pointer">
                            <x-ui.transaction-list-item
                                :description="$tx->description ?? 'Transaksi'"
                                :category="$tx->category->name ?? 'Umum'"
                                :date="$tx->transaction_date->format('d M Y')"
                                :amount="$tx->amount"
                                :type="$tx->type"
                            />
                        </div>
                    @endforeach
                    <div class="pt-4">
                        {{ $transactions->links() }}
                    </div>
                @endif
            </div>

            {{-- Detail Panel --}}
            <div class="bg-surface border border-border rounded-lg p-6 shadow-card space-y-4">
                <h3 class="text-lg font-bold font-display text-on-surface">Detail Transaksi</h3>
                <template x-if="selectedTx">
                    <div class="space-y-4 text-xs">
                        <div class="p-4 rounded-md bg-surface-alt border border-border space-y-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                  :class="selectedTx.type === 'income' ? 'bg-success-bg text-success' : 'bg-hero-accent text-primary'"
                                  x-text="selectedTx.type === 'income' ? 'Pemasukan' : 'Pengeluaran'"></span>
                            <h4 class="text-xl font-bold font-display text-on-surface" x-text="'Rp ' + Number(selectedTx.amount).toLocaleString('id-ID')"></h4>
                            <p class="text-on-surface-variant font-semibold" x-text="selectedTx.description"></p>
                        </div>
                        <div class="space-y-2 text-on-surface-variant">
                            <div class="flex justify-between"><span>Tanggal:</span><span class="font-bold text-on-surface" x-text="selectedTx.transaction_date"></span></div>
                            <div class="flex justify-between"><span>Kategori:</span><span class="font-bold text-on-surface" x-text="selectedTx.category ? selectedTx.category.name : 'Umum'"></span></div>
                            <div class="flex justify-between"><span>Sumber:</span><span class="font-bold text-on-surface" x-text="selectedTx.source"></span></div>
                            <div class="flex justify-between"><span>Metode:</span><span class="font-bold text-on-surface" x-text="selectedTx.payment_method"></span></div>
                        </div>
                        <form :action="'/sikas/transactions/' + selectedTx.id" method="POST" class="pt-4 border-t border-border">
                            @csrf
                            @method('DELETE')
                            <button type="submit" onclick="return confirm('Hapus transaksi ini?')" class="w-full py-2 bg-error/10 text-error font-semibold text-xs rounded hover:bg-error/20 transition">
                                Hapus Transaksi
                            </button>
                        </form>
                    </div>
                </template>
                <template x-if="!selectedTx">
                    <div class="p-6 text-center text-on-surface-variant/70 text-xs font-medium">
                        Klik salah satu item transaksi di samping untuk melihat rincian detail.
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-layouts.app>
