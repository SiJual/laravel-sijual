<x-layouts.app title="Transaction History — SiKas" activeNav="sikas" :hideTopBar="true">
    <div x-data="transactionsPage()" class="p-6 lg:p-10 space-y-6 max-w-7xl mx-auto w-full">
        
        {{-- Flash Alert --}}
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-transition class="p-4 rounded-2xl bg-[#DCE7DD] border border-[#4E8057]/30 text-[#4E8057] text-sm font-semibold flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button @click="show = false" class="text-xs font-bold opacity-70 hover:opacity-100">&times;</button>
            </div>
        @endif

        {{-- Page Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl lg:text-4xl font-serif font-bold text-gray-900 tracking-tight">Transaction History</h1>
                <p class="text-sm text-gray-500 font-medium mt-1">Review and manage your financial records.</p>
            </div>
            <div class="flex items-center gap-3">
                <button @click="showManualModal = true"
                        class="px-5 py-2.5 bg-[#9D3D2B] text-white font-semibold text-sm rounded-full shadow-sm hover:bg-[#9D3D2B]/90 transition flex items-center gap-2">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Catat Transaksi
                </button>
                <a href="{{ route('sikas.dashboard') }}" class="text-sm font-semibold text-[#9D3D2B] hover:underline flex items-center gap-1">
                    ← Dasbor SiKas
                </a>
            </div>
        </div>

        {{-- Filter Bar (Matching Image 2) --}}
        <form method="GET" action="{{ route('sikas.transactions.index') }}" class="bg-white rounded-2xl border border-[#F2E8E5] p-3 shadow-sm flex flex-wrap items-center gap-2 sm:gap-3">
            {{-- Search input --}}
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3.5 top-2.5 size-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search transactions, notes..."
                       class="w-full pl-10 pr-4 py-2 bg-gray-50/70 border border-gray-200 rounded-xl text-xs text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition font-medium">
            </div>

            {{-- Type Filter --}}
            <div class="relative">
                <select name="type" onchange="this.form.submit()" class="px-3.5 py-2 bg-gray-50/70 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 focus:outline-none focus:border-[#9D3D2B] focus:bg-white cursor-pointer">
                    <option value="">Semua Type</option>
                    <option value="income" {{ request('type') === 'income' ? 'selected' : '' }}>Income (+)</option>
                    <option value="expense" {{ request('type') === 'expense' ? 'selected' : '' }}>Expense (-)</option>
                </select>
            </div>

            {{-- Category Filter --}}
            <div class="relative">
                <select name="category_id" onchange="this.form.submit()" class="px-3.5 py-2 bg-gray-50/70 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 focus:outline-none focus:border-[#9D3D2B] focus:bg-white cursor-pointer">
                    <option value="">Semua Category</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') === $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="px-4 py-2 bg-gray-900 text-white font-semibold text-xs rounded-xl shadow-sm hover:bg-black transition">
                Filter
            </button>
            @if(request()->hasAny(['search', 'type', 'category_id']))
                <a href="{{ route('sikas.transactions.index') }}" class="text-xs text-red-600 font-semibold hover:underline px-2">Reset</a>
            @endif
        </form>

        {{-- Transactions Main Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            
            {{-- Left Column: Transaction List (Matching Image 2) --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between text-[11px] font-bold text-gray-400 uppercase tracking-widest px-1">
                    <span>DAFTAR TRANSAKSI</span>
                    <span>Total: {{ $transactions->total() }} Catatan</span>
                </div>

                @if($transactions->isEmpty())
                    <div class="p-12 bg-white rounded-3xl border border-[#F2E8E5] text-center text-gray-400 text-sm font-medium">
                        Tidak ada transaksi yang cocok dengan filter pencarian.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($transactions as $tx)
                            <div @click="selectedTx = {{ json_encode($tx) }}"
                                 :class="selectedTx?.id === '{{ $tx->id }}' ? 'ring-2 ring-[#9D3D2B]/30 border-[#9D3D2B]/40 bg-[#FAF4F2]/30' : 'bg-white hover:border-gray-300'"
                                 class="relative p-4 rounded-2xl border border-[#F2E8E5] shadow-sm transition flex items-center justify-between cursor-pointer overflow-hidden">
                                
                                {{-- Colored vertical indicator on left edge (Red for Expense, Green for Income) --}}
                                <div class="absolute left-0 top-3 bottom-3 w-1 rounded-r-full {{ $tx->type === 'income' ? 'bg-[#4E8057]' : 'bg-[#9D3D2B]' }}"></div>

                                <div class="flex items-center gap-3.5 pl-2">
                                    {{-- Left Squircle Icon --}}
                                    <div class="size-11 rounded-2xl {{ $tx->type === 'income' ? 'bg-gray-100 text-gray-700' : 'bg-[#DCE7DD] text-[#4E8057]' }} flex items-center justify-center shrink-0">
                                        @if($tx->type === 'income')
                                            {{-- Shopping bag / Sales icon --}}
                                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                        @else
                                            {{-- Box / Inventory icon --}}
                                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                                        @endif
                                    </div>

                                    <div>
                                        <h4 class="text-sm font-bold text-gray-900 leading-snug">{{ $tx->description ?? 'Transaksi' }}</h4>
                                        <p class="text-xs text-gray-500 font-medium mt-0.5">
                                            {{ $tx->type === 'income' ? 'Income' : 'Expense' }} • {{ $tx->category->name ?? 'Umum' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="text-right shrink-0">
                                    <span class="text-sm font-bold {{ $tx->type === 'income' ? 'text-[#4E8057]' : 'text-gray-900' }}">
                                        {{ $tx->type === 'income' ? '+' : '-' }} Rp {{ number_format($tx->amount, 0, ',', '.') }}
                                    </span>
                                    <p class="text-[11px] text-gray-400 font-medium mt-0.5">{{ $tx->transaction_date ? $tx->transaction_date->isoFormat('D MMM Y, HH:mm') : '' }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="pt-4">
                        {{ $transactions->links() }}
                    </div>
                @endif
            </div>

            {{-- Right Column: Transaction Detail Panel (Matching Image 2) --}}
            <div class="bg-white border border-[#F2E8E5] rounded-3xl p-6 lg:p-7 shadow-sm sticky top-6 space-y-6 relative overflow-hidden">
                {{-- Top subtle accent strip --}}
                <div class="absolute top-0 left-0 right-0 h-1.5" :class="selectedTx?.type === 'income' ? 'bg-[#4E8057]' : 'bg-[#9D3D2B]'"></div>

                <div class="flex items-center justify-between pt-1">
                    <h3 class="text-lg font-serif font-bold text-gray-900">Transaction Detail</h3>
                    <button @click="selectedTx = null" class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
                </div>

                <template x-if="selectedTx">
                    <div class="space-y-6">
                        {{-- Hero Amount Display --}}
                        <div class="text-center py-2">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-widest block mb-1"
                                  x-text="selectedTx.type === 'income' ? 'INCOME' : 'EXPENSE'"></span>
                            <h2 class="text-3xl lg:text-4xl font-serif font-extrabold text-gray-900 tracking-tight"
                                x-text="(selectedTx.type === 'income' ? '+ ' : '- ') + 'Rp ' + Number(selectedTx.amount).toLocaleString('id-ID')"></h2>
                            <div class="mt-2">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-50 text-green-700 text-xs font-semibold rounded-full">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                    Completed
                                </span>
                            </div>
                        </div>

                        {{-- Metadata List --}}
                        <div class="bg-gray-50/60 rounded-2xl p-4 border border-gray-100 space-y-3 text-xs">
                            <div class="flex justify-between items-center text-gray-500">
                                <span>Date & Time</span>
                                <span class="font-bold text-gray-900" x-text="formatFullDate(selectedTx.transaction_date) + ' • ' + formatTime(selectedTx.transaction_date)"></span>
                            </div>
                            <div class="flex justify-between items-center text-gray-500 border-t border-gray-100 pt-2">
                                <span>Merchant / Sumber</span>
                                <span class="font-bold text-gray-900" x-text="selectedTx.source === 'qris' ? 'QRIS Settlement' : (selectedTx.notes || 'Kasir Outlet')"></span>
                            </div>
                            <div class="flex justify-between items-center text-gray-500 border-t border-gray-100 pt-2">
                                <span>Category</span>
                                <span class="font-bold text-gray-900" x-text="selectedTx.category ? selectedTx.category.name : 'Inventory'"></span>
                            </div>
                            <div class="flex justify-between items-center text-gray-500 border-t border-gray-100 pt-2">
                                <span>Payment Method</span>
                                <span class="font-bold text-gray-900 uppercase" x-text="selectedTx.payment_method || 'CASH'"></span>
                            </div>
                        </div>

                        {{-- ✨ SiKas Insights Card (Matching Image 2) --}}
                        <div class="bg-[#FAF4F2] border border-[#F2E8E5] rounded-2xl p-5 relative overflow-hidden space-y-2">
                            {{-- Decorative Watermark Sparkles in bottom right --}}
                            <div class="absolute -right-2 -bottom-2 text-[#9D3D2B]/10 pointer-events-none">
                                <svg class="size-20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0l3.09 8.26L24 12l-8.91 3.74L12 24l-3.09-8.26L0 12l8.91-3.74L12 0z"/></svg>
                            </div>

                            <div class="flex items-center gap-1.5 text-xs font-bold text-[#9D3D2B]">
                                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/></svg>
                                <span>SiKas Insights</span>
                            </div>
                            <p class="text-xs text-gray-700 leading-relaxed font-medium relative z-10"
                               x-text="selectedTx.type === 'expense'
                                       ? 'Pengeluaran ini terkategori dengan baik. Menjaga batas belanja operasional tetap efisien akan membantu menjaga rasio kas bersih bisnis Anda tetap stabil.'
                                       : 'Pemasukan ini telah terverifikasi dan tercatat ke kas usaha. Lanjutkan pemantauan tren harian untuk memaksimalkan omset bulanan.'">
                            </p>
                        </div>

                        {{-- Action Buttons (Edit & Receipt/Delete) (Matching Image 2) --}}
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <button type="button"
                                    @click="editTx = {
                                        id: selectedTx.id,
                                        type: selectedTx.type,
                                        amount: selectedTx.amount,
                                        description: selectedTx.description,
                                        category_id: selectedTx.category_id,
                                        transaction_date: formatForInput(selectedTx.transaction_date),
                                        payment_method: selectedTx.payment_method || 'cash',
                                        notes: selectedTx.notes || ''
                                    }; showEditModal = true"
                                    class="py-2.5 border border-[#9D3D2B] text-[#9D3D2B] font-bold text-xs rounded-xl hover:bg-[#FAF4F2] transition text-center shadow-sm">
                                Edit
                            </button>

                            <form :action="'/sikas/transactions/' + selectedTx.id" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" onclick="return confirm('Hapus catatan transaksi ini?')"
                                        class="w-full py-2.5 bg-gray-100 hover:bg-red-50 hover:text-red-700 text-gray-700 font-bold text-xs rounded-xl transition flex items-center justify-center gap-1.5 shadow-sm">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                </template>

                <template x-if="!selectedTx">
                    <div class="p-8 text-center text-gray-400 text-xs font-medium">
                        Pilih salah satu transaksi dari daftar untuk melihat detail lengkap.
                    </div>
                </template>
            </div>
        </div>

        {{-- Manual Transaction Modal --}}
        <div x-show="showManualModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" style="display:none;">
            <div @click.away="showManualModal = false" class="bg-white rounded-3xl border border-[#F2E8E5] p-6 lg:p-8 w-full max-w-lg shadow-2xl space-y-6">
                <div class="flex items-center justify-between pb-3 border-b border-[#F2E8E5]">
                    <div>
                        <h3 class="text-xl font-serif font-bold text-gray-900">Catat Transaksi Baru</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Input data pemasukan atau pengeluaran keuangan usaha.</p>
                    </div>
                    <button @click="showManualModal = false" class="size-8 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 flex items-center justify-center font-bold text-lg">&times;</button>
                </div>

                <form action="{{ route('sikas.transactions.store') }}" method="POST" class="space-y-4" x-data="{ txType: 'income', txAmount: '' }">
                    @csrf
                    <input type="hidden" name="source" value="manual">

                    {{-- Type selector (Income / Expense) --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">Jenis Transaksi</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="income" x-model="txType" class="sr-only">
                                <div :class="txType === 'income' ? 'bg-[#DCE7DD] border-[#4E8057] text-[#4E8057] font-bold ring-2 ring-[#4E8057]/20' : 'bg-gray-50 border-gray-200 text-gray-600'"
                                     class="p-3 rounded-xl border flex items-center justify-center gap-2 text-sm transition">
                                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                                    Pemasukan (+)
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="expense" x-model="txType" class="sr-only">
                                <div :class="txType === 'expense' ? 'bg-[#FCF0ED] border-[#9D3D2B] text-[#9D3D2B] font-bold ring-2 ring-[#9D3D2B]/20' : 'bg-gray-50 border-gray-200 text-gray-600'"
                                     class="p-3 rounded-xl border flex items-center justify-center gap-2 text-sm transition">
                                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                    Pengeluaran (-)
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Amount --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider">Nominal</label>
                            <span class="text-xs font-bold text-[#9D3D2B]" x-show="txAmount" x-text="'Rp ' + formatRupiah(txAmount)"></span>
                        </div>
                        <div class="relative">
                            <span class="absolute left-4 top-3 text-sm font-bold text-gray-400">Rp</span>
                            <input type="number" name="amount" x-model="txAmount" required min="1" placeholder="50000"
                                   class="w-full pl-12 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-base font-bold text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                        </div>
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">Deskripsi Transaksi</label>
                        <input type="text" name="description" required placeholder="Contoh: Penjualan Paket Kopi Espresso"
                               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                    </div>

                    {{-- Category & Payment Method Grid --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider">Kategori</label>
                                <button type="button" @click="newCatType = txType; showCategoryModal = true" class="text-[11px] font-bold text-[#9D3D2B] hover:underline">+ Kategori</button>
                            </div>
                            <select name="category_id" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                                <option value="">Pilih Kategori...</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" x-show="'{{ $cat->type }}' === txType">
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">Metode Bayar</label>
                            <select name="payment_method" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                                <option value="cash">Tunai / Cash</option>
                                <option value="qris">QRIS</option>
                                <option value="transfer">Transfer Bank</option>
                                <option value="ewallet">E-Wallet</option>
                            </select>
                        </div>
                    </div>

                    {{-- Date & Notes --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">Tanggal & Waktu</label>
                            <input type="datetime-local" name="transaction_date" required value="{{ date('Y-m-d\TH:i') }}"
                                   class="w-full px-3.5 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">Catatan (Opsional)</label>
                            <input type="text" name="notes" placeholder="Catatan kecil..."
                                   class="w-full px-3.5 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                        </div>
                    </div>

                    {{-- Buttons --}}
                    <div class="flex gap-3 pt-3 border-t border-[#F2E8E5]">
                        <button type="button" @click="showManualModal = false" class="flex-1 py-2.5 border border-gray-200 text-gray-600 font-semibold text-sm rounded-xl hover:bg-gray-50 transition">
                            Batal
                        </button>
                        <button type="submit" class="flex-1 py-2.5 bg-[#9D3D2B] text-white font-semibold text-sm rounded-xl hover:bg-[#9D3D2B]/90 transition shadow-sm">
                            Simpan Transaksi
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Transaction Modal --}}
        <div x-show="showEditModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" style="display:none;">
            <div @click.away="showEditModal = false" class="bg-white rounded-3xl border border-[#F2E8E5] p-6 lg:p-8 w-full max-w-lg shadow-2xl space-y-6">
                <div class="flex items-center justify-between pb-3 border-b border-[#F2E8E5]">
                    <div>
                        <h3 class="text-xl font-serif font-bold text-gray-900">Edit Transaksi</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Perbarui rincian transaksi ini.</p>
                    </div>
                    <button @click="showEditModal = false" class="size-8 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 flex items-center justify-center font-bold text-lg">&times;</button>
                </div>

                <form :action="'/sikas/transactions/' + editTx.id" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    {{-- Type selector (Income / Expense) --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">Jenis Transaksi</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="income" x-model="editTx.type" class="sr-only">
                                <div :class="editTx.type === 'income' ? 'bg-[#DCE7DD] border-[#4E8057] text-[#4E8057] font-bold ring-2 ring-[#4E8057]/20' : 'bg-gray-50 border-gray-200 text-gray-600'"
                                     class="p-3 rounded-xl border flex items-center justify-center gap-2 text-sm transition">
                                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                                    Pemasukan (+)
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="expense" x-model="editTx.type" class="sr-only">
                                <div :class="editTx.type === 'expense' ? 'bg-[#FCF0ED] border-[#9D3D2B] text-[#9D3D2B] font-bold ring-2 ring-[#9D3D2B]/20' : 'bg-gray-50 border-gray-200 text-gray-600'"
                                     class="p-3 rounded-xl border flex items-center justify-center gap-2 text-sm transition">
                                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                    Pengeluaran (-)
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Amount --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider">Nominal</label>
                            <span class="text-xs font-bold text-[#9D3D2B]" x-show="editTx.amount" x-text="'Rp ' + formatRupiah(editTx.amount)"></span>
                        </div>
                        <div class="relative">
                            <span class="absolute left-4 top-3 text-sm font-bold text-gray-400">Rp</span>
                            <input type="number" name="amount" x-model="editTx.amount" required min="1"
                                   class="w-full pl-12 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-base font-bold text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                        </div>
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">Deskripsi Transaksi</label>
                        <input type="text" name="description" x-model="editTx.description" required
                               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                    </div>

                    {{-- Category & Payment Method Grid --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">Kategori</label>
                            <select name="category_id" x-model="editTx.category_id" required class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" x-show="'{{ $cat->type }}' === editTx.type">
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">Metode Bayar</label>
                            <select name="payment_method" x-model="editTx.payment_method" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                                <option value="cash">Tunai / Cash</option>
                                <option value="qris">QRIS</option>
                                <option value="transfer">Transfer Bank</option>
                                <option value="ewallet">E-Wallet</option>
                            </select>
                        </div>
                    </div>

                    {{-- Date & Notes --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">Tanggal & Waktu</label>
                            <input type="datetime-local" name="transaction_date" x-model="editTx.transaction_date" required
                                   class="w-full px-3.5 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">Catatan</label>
                            <input type="text" name="notes" x-model="editTx.notes" placeholder="Catatan kecil..."
                                   class="w-full px-3.5 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                        </div>
                    </div>

                    {{-- Buttons --}}
                    <div class="flex gap-3 pt-3 border-t border-[#F2E8E5]">
                        <button type="button" @click="showEditModal = false" class="flex-1 py-2.5 border border-gray-200 text-gray-600 font-semibold text-sm rounded-xl hover:bg-gray-50 transition">
                            Batal
                        </button>
                        <button type="submit" class="flex-1 py-2.5 bg-[#9D3D2B] text-white font-semibold text-sm rounded-xl hover:bg-[#9D3D2B]/90 transition shadow-sm">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Quick Add Category Modal --}}
        <div x-show="showCategoryModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" style="display:none;">
            <div @click.away="showCategoryModal = false" class="bg-white rounded-3xl border border-[#F2E8E5] p-6 w-full max-w-sm shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-[#F2E8E5]">
                    <h4 class="font-serif font-bold text-gray-900 text-base">Tambah Kategori Baru</h4>
                    <button @click="showCategoryModal = false" class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
                </div>
                <form action="{{ route('sikas.categories.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Nama Kategori</label>
                        <input type="text" name="name" required placeholder="Contoh: Bahan Kopi"
                               class="w-full px-3.5 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:border-[#9D3D2B]">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Jenis</label>
                        <select name="type" x-model="newCatType" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-900">
                            <option value="income">Pemasukan (+)</option>
                            <option value="expense">Pengeluaran (-)</option>
                        </select>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="showCategoryModal = false" class="flex-1 py-2 border border-gray-200 text-xs font-semibold rounded-xl">Batal</button>
                        <button type="submit" class="flex-1 py-2 bg-[#9D3D2B] text-white text-xs font-bold rounded-xl shadow">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function formatForInput(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr.substring(0, 16);
            const pad = (n) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        }

        function transactionsPage() {
            return {
                selectedTx: @json($transactions->isNotEmpty() ? $transactions->first() : null),
                showManualModal: false,
                showEditModal: false,
                showCategoryModal: false,
                newCatName: '',
                newCatType: 'expense',
                editTx: { id: '', type: 'income', amount: 0, description: '', category_id: '', transaction_date: '', payment_method: 'cash', notes: '' },
                formatRupiah(num) {
                    return Number(num || 0).toLocaleString('id-ID');
                },
                formatTime(dateStr) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr);
                    return isNaN(d.getTime()) ? '' : d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                },
                formatFullDate(dateStr) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr);
                    return isNaN(d.getTime()) ? dateStr.substring(0, 10) : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                }
            };
        }
    </script>
    @endpush
</x-layouts.app>
