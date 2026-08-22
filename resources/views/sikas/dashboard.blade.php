<x-layouts.app title="SiKas — Keuangan Pintar" activeNav="sikas" :hideTopBar="true">
    <div x-data="sikasDashboard()" @notify-toast.window="showNotification($event.detail.message, $event.detail.type || 'success')" class="flex h-full w-full">
        
        {{-- Main Content Column --}}
        <div class="flex-1 overflow-y-auto p-6 lg:p-10 flex flex-col gap-8">
            
            {{-- Dynamic Toast Alert --}}
            <div x-show="showToast" x-transition
                 :class="{
                    'bg-[#DCE7DD] border-[#4E8057]/30 text-[#4E8057]': toastType === 'success',
                    'bg-[#EBF3FA] border-[#3B82F6]/30 text-[#1E40AF]': toastType === 'info',
                    'bg-[#FCF0ED] border-[#9D3D2B]/30 text-[#9D3D2B]': toastType === 'error'
                 }"
                 class="p-4 rounded-2xl border text-sm font-semibold flex items-center justify-between shadow-sm transition-all" style="display:none;">
                <div class="flex items-center gap-2.5">
                    <template x-if="toastType === 'success'">
                        <svg class="size-5 text-[#4E8057] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </template>
                    <template x-if="toastType === 'info'">
                        <svg class="size-5 text-blue-600 animate-spin shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                    </template>
                    <template x-if="toastType === 'error'">
                        <svg class="size-5 text-[#9D3D2B] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                    </template>
                    <span x-text="toastMessage"></span>
                </div>
                <button @click="showToast = false" class="text-xs font-bold opacity-70 hover:opacity-100 ml-3">&times;</button>
            </div>
            
            {{-- Flash Alerts --}}
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-transition class="p-4 rounded-2xl bg-[#DCE7DD] border border-[#4E8057]/30 text-[#4E8057] text-sm font-semibold flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button @click="show = false" class="text-xs font-bold opacity-70 hover:opacity-100">&times;</button>
                </div>
            @endif

            @if(isset($errors) && $errors->any())
                <div x-data="{ show: true }" x-show="show" x-transition class="p-4 rounded-2xl bg-[#FCF0ED] border border-[#9D3D2B]/30 text-[#9D3D2B] text-sm font-semibold flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                    <button @click="show = false" class="text-xs font-bold opacity-70 hover:opacity-100">&times;</button>
                </div>
            @endif

            {{-- Header --}}
            @php
                $hour = now()->hour;
                $timeGreeting = $hour < 11 ? 'pagi' : ($hour < 15 ? 'siang' : ($hour < 18 ? 'sore' : 'malam'));
                $displayName = $profile->business_name ?? Auth::user()->name ?? 'Sahabat UMKM';
            @endphp
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-3xl lg:text-4xl font-serif font-bold text-gray-900 tracking-tight">Dasbor Keuangan</h1>
                    <p class="text-sm lg:text-base text-gray-500 font-medium mt-1">Selamat {{ $timeGreeting }}, {{ $displayName }}. Mari kelola arus kas Anda hari ini.</p>
                </div>
                <div class="flex items-center gap-3 shrink-0 self-end sm:self-auto">
                    <button @click="showManualModal = true"
                            class="px-5 py-2.5 bg-[#9D3D2B] text-white text-sm font-semibold rounded-full shadow-sm hover:bg-[#9D3D2B]/90 transition flex items-center gap-2">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Catat
                    </button>
                </div>
            </div>

            {{-- Voice Input Card --}}
            <div x-data="voiceInput()" class="bg-white rounded-[28px] border border-[#F2E8E5] p-6 lg:p-8 flex flex-col md:flex-row justify-between items-center relative overflow-hidden gap-6 min-h-[175px] shadow-sm">
                
                {{-- Background decorative vertical bars --}}
                <div class="absolute right-8 lg:right-20 top-0 flex gap-4 opacity-40 z-0 pointer-events-none">
                    <div class="w-6 h-16 bg-gray-100 rounded-b-md"></div>
                    <div class="w-6 h-28 bg-gray-100 rounded-b-md"></div>
                    <div class="w-6 h-40 bg-gray-100 rounded-b-md"></div>
                </div>

                <div class="flex-1 max-w-xl relative z-10 w-full">
                    <h2 class="text-2xl lg:text-[28px] font-serif font-bold text-gray-900 tracking-tight">Input Suara Pintar</h2>
                    <p class="text-sm lg:text-base text-[#6B5A57] font-medium leading-relaxed mt-2" x-text="statusMessage">
                        "Tap mikrofon untuk merekam transaksi, bicara dengan bahasa sehari-hari."
                    </p>
                    <div class="flex items-center gap-3 mt-4 lg:mt-5">
                        <div class="w-8 h-[3px] rounded-full bg-[#BD5B43] shrink-0"></div>
                        <span class="text-[#BD5B43] font-bold text-xs tracking-widest uppercase">Catat Transaksi Suara</span>
                    </div>
                </div>
                
                {{-- Voice Button Area with concentric rings --}}
                <div class="relative z-10 shrink-0 size-32 lg:size-36 flex items-center justify-center md:mr-4 lg:mr-8">
                    {{-- Concentric rings --}}
                    <div class="absolute size-32 lg:size-36 border border-[#F2E8E5] rounded-full pointer-events-none" :class="{'animate-ping opacity-30 border-red-400': recording}"></div>
                    <div class="absolute size-24 lg:size-28 border border-[#F2E8E5] rounded-full pointer-events-none"></div>
                    
                    <button @click="toggleRecording()"
                            type="button"
                            class="relative size-16 lg:size-20 rounded-full flex items-center justify-center text-white shadow-[0_0_0_6px_white,0_6px_16px_rgba(0,0,0,0.12)] hover:scale-105 transition-transform z-10 shrink-0"
                            :class="recording ? 'bg-red-600 animate-pulse' : 'bg-[#BD5B43]'"
                            :disabled="loading"
                            title="Klik untuk bicara catat transaksi">
                        
                        {{-- Loading Spinner --}}
                        <svg x-show="loading" class="size-7 animate-spin text-white" fill="none" viewBox="0 0 24 24" style="display:none;">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>

                        {{-- Recording Stop Square --}}
                        <svg x-show="!loading && recording" class="size-6 text-white" fill="currentColor" viewBox="0 0 24 24" style="display:none;">
                            <rect x="5" y="5" width="14" height="14" rx="2"></rect>
                        </svg>

                        {{-- Microphone Icon (Idle) --}}
                        <svg x-show="!loading && !recording" class="size-7 lg:size-8 text-white" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                            <line x1="12" y1="19" x2="12" y2="22"></line>
                        </svg>
                    </button>
                </div>

                {{-- Voice Confirmation Modal --}}
                <div x-show="showConfirm" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" style="display:none;">
                    <div @click.away="showConfirm = false" class="bg-white rounded-3xl border border-[#F2E8E5] p-6 lg:p-8 w-full max-w-md shadow-2xl space-y-5">
                        <div>
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-50 text-green-700 text-xs font-bold rounded-full mb-2">
                                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                AI Voice Disarikan
                            </div>
                            <h3 class="text-xl font-serif font-bold text-gray-900">Konfirmasi Transaksi AI</h3>
                            <p class="text-xs text-gray-500 mt-1">Periksa dan sesuaikan data sebelum menyimpan ke pembukuan:</p>
                        </div>

                        <form @submit.prevent="submitVoiceTransaction($event)" action="{{ route('sikas.transactions.store') }}" method="POST" class="space-y-4">
                            @csrf
                            <input type="hidden" name="source" value="voice">
                            <input type="hidden" name="category_name" :value="aiResult?.category">
                            <input type="hidden" name="transaction_date" value="{{ date('Y-m-d H:i:s') }}">

                            <div class="bg-[#FAF4F2] rounded-2xl p-4 border border-[#F2E8E5] space-y-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Deskripsi</label>
                                    <input type="text" name="description" x-model="aiResult.description" required
                                           class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-900 focus:outline-none focus:border-[#9D3D2B]">
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Nominal</label>
                                        <span class="text-xs font-bold text-[#9D3D2B]" x-show="aiResult.amount" x-text="'Rp ' + Number(aiResult.amount || 0).toLocaleString('id-ID')"></span>
                                    </div>
                                    <div class="relative">
                                        <span class="absolute left-3.5 top-2.5 text-xs font-bold text-gray-400">Rp</span>
                                        <input type="number" name="amount" x-model="aiResult.amount" required min="1"
                                               class="w-full pl-10 pr-3 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-900 focus:outline-none focus:border-[#9D3D2B]">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Jenis Transaksi</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" @click="aiResult.type = 'income'"
                                                :class="aiResult.type === 'income' ? 'bg-[#DCE7DD] text-[#4E8057] border-[#4E8057] font-bold' : 'bg-white text-gray-600 border-gray-200'"
                                                class="py-2 text-xs rounded-xl border transition text-center">
                                            + Pemasukan
                                        </button>
                                        <button type="button" @click="aiResult.type = 'expense'"
                                                :class="aiResult.type === 'expense' ? 'bg-[#FCF0ED] text-[#9D3D2B] border-[#9D3D2B] font-bold' : 'bg-white text-gray-600 border-gray-200'"
                                                class="py-2 text-xs rounded-xl border transition text-center">
                                            - Pengeluaran
                                        </button>
                                    </div>
                                    <input type="hidden" name="type" :value="aiResult.type">
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Kategori</label>
                                    <select name="category_id" x-model="aiResult.category_id" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-900 focus:outline-none focus:border-[#9D3D2B]">
                                        <option value="">Pilih Kategori...</option>
                                        <template x-for="cat in allCategories" :key="cat.id">
                                            <option :value="cat.id" x-show="cat.type === aiResult.type" x-text="cat.name"></option>
                                        </template>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Metode Bayar</label>
                                    <select name="payment_method" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-900 focus:outline-none focus:border-[#9D3D2B]">
                                        <option value="cash">Tunai / Dompet</option>
                                        <option value="qris">QRIS</option>
                                        <option value="transfer">Transfer Bank</option>
                                        <option value="ewallet">E-Wallet (Gopay/OVO/ShopeePay)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex gap-2 pt-2">
                                <button type="button" @click="showConfirm = false" class="flex-1 py-2.5 border border-gray-200 text-gray-600 text-xs font-bold rounded-xl hover:bg-gray-50 transition">
                                    Batal
                                </button>
                                <button type="submit" class="flex-1 py-2.5 bg-[#9D3D2B] text-white font-semibold text-sm rounded-xl hover:bg-[#9D3D2B]/90 transition shadow-sm">
                                    Simpan Transaksi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Manual Transaction Modal --}}
            <div x-show="showManualModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" style="display:none;">
                <div @click.away="showManualModal = false" class="bg-white rounded-3xl border border-[#F2E8E5] p-6 lg:p-8 w-full max-w-lg shadow-2xl space-y-6">
                    <div class="flex items-center justify-between pb-3 border-b border-[#F2E8E5]">
                        <div>
                            <h3 class="text-xl font-serif font-bold text-gray-900">Catat Transaksi Manual</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Input detail pemasukan atau pengeluaran bisnis Anda.</p>
                        </div>
                        <button @click="showManualModal = false" class="size-8 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 flex items-center justify-center font-bold text-lg">&times;</button>
                    </div>

                    <form @submit.prevent="saveManualTx($event)" action="{{ route('sikas.transactions.store') }}" method="POST" class="space-y-4" x-data="{ txType: 'income', txAmount: '' }">
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
                            <input type="text" name="description" required placeholder="Contoh: Penjualan 10 Box Kopi Arabika"
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
                                    <template x-for="cat in allCategories" :key="cat.id">
                                        <option :value="cat.id" x-show="cat.type === txType" x-text="cat.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1.5">Metode Bayar</label>
                                <select name="payment_method" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                                    <option value="cash">Tunai / Cash</option>
                                    <option value="qris">QRIS</option>
                                    <option value="transfer">Transfer Bank</option>
                                    <option value="ewallet">E-Wallet (GoPay/OVO/Dana)</option>
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

            {{-- Quick Add Category Modal --}}
            <div x-show="showCategoryModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" style="display:none;">
                <div @click.away="showCategoryModal = false" class="bg-white rounded-3xl border border-[#F2E8E5] p-6 w-full max-w-sm shadow-2xl space-y-4">
                    <div class="flex items-center justify-between pb-2 border-b border-[#F2E8E5]">
                        <h4 class="font-serif font-bold text-gray-900 text-base">Tambah Kategori Baru</h4>
                        <button @click="showCategoryModal = false" class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
                    </div>
                    <form @submit.prevent="saveQuickCategory($event)" action="{{ route('sikas.categories.store') }}" method="POST" class="space-y-3">
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

            {{-- MODAL 1: Detail Saldo Kas Toko --}}
            <div x-show="showBalanceModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" style="display:none;">
                <div @click.away="showBalanceModal = false" class="bg-white rounded-3xl border border-[#F2E8E5] p-6 lg:p-8 w-full max-w-lg shadow-2xl space-y-6">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="size-11 rounded-2xl bg-[#DCE7DD] text-[#4E8057] flex items-center justify-center shrink-0">
                                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v10.5m0-10.5h10.5a.75.75 0 0 1 .75.75v.75m-11.25 9h11.25m-11.25 0h-.75A.75.75 0 0 1 0 15V4.5M12 4.5v.75a.75.75 0 0 1-.75.75H3.75m11.25 9H18a.75.75 0 0 0 .75-.75V6a.75.75 0 0 0-.75-.75h-.75M12 4.5H3.75"/></svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-serif font-bold text-gray-900">Rincian Saldo Kas Toko</h3>
                                <p class="text-xs text-gray-500 font-medium mt-0.5">Ringkasan arus kas bersih berjalan UMKM Anda</p>
                            </div>
                        </div>
                        <button @click="showBalanceModal = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                    </div>

                    {{-- Saldo Utama Box --}}
                    <div class="p-5 rounded-2xl bg-[#FAF4F2] border border-[#F2E8E5] text-center">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Sisa Saldo Kas</p>
                        <div class="text-3xl font-serif font-bold text-gray-900 mt-1">
                            Rp {{ number_format($totalBalance ?? 0, 0, ',', '.') }}
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            Pertumbuhan: <span class="font-bold {{ $growthPercent >= 0 ? 'text-[#4E8057]' : 'text-[#9D3D2B]' }}">{{ $growthPercent >= 0 ? '+' : '' }}{{ $growthPercent }}%</span> dibanding bulan lalu
                        </p>
                    </div>

                    {{-- Perbandingan Akumulasi & Bulan Ini --}}
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="p-4 rounded-xl bg-white border border-gray-200">
                            <p class="text-[11px] font-bold text-gray-500 uppercase">Pemasukan Bulan Ini</p>
                            <p class="text-base font-bold text-[#4E8057] mt-1">Rp {{ number_format($thisMonthIncome ?? 0, 0, ',', '.') }}</p>
                            <p class="text-[11px] text-gray-400 mt-1">Semua: Rp {{ number_format($allTimeIncome ?? 0, 0, ',', '.') }}</p>
                        </div>
                        <div class="p-4 rounded-xl bg-white border border-gray-200">
                            <p class="text-[11px] font-bold text-gray-500 uppercase">Pengeluaran Bulan Ini</p>
                            <p class="text-base font-bold text-[#9D3D2B] mt-1">Rp {{ number_format($thisMonthExpense ?? 0, 0, ',', '.') }}</p>
                            <p class="text-[11px] text-gray-400 mt-1">Semua: Rp {{ number_format($allTimeExpense ?? 0, 0, ',', '.') }}</p>
                        </div>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <a href="{{ route('sikas.transactions.index') }}" class="w-full py-3 bg-gray-900 hover:bg-black text-white text-xs font-bold rounded-xl text-center shadow transition flex items-center justify-center gap-2">
                            <span>Buka Buku Kas & Semua Transaksi</span>
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            {{-- MODAL 2: Detail Pengeluaran Bulan Ini --}}
            <div x-show="showExpenseModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" style="display:none;">
                <div @click.away="showExpenseModal = false" class="bg-white rounded-3xl border border-[#F2E8E5] p-6 lg:p-8 w-full max-w-lg shadow-2xl space-y-6">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="size-11 rounded-2xl bg-[#FCF0ED] text-[#9D3D2B] flex items-center justify-center shrink-0">
                                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-serif font-bold text-gray-900">Rincian Pengeluaran Bulan Ini</h3>
                                <p class="text-xs text-gray-500 font-medium mt-0.5">{{ now()->locale('id')->isoFormat('MMMM Y') }}</p>
                            </div>
                        </div>
                        <button @click="showExpenseModal = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                    </div>

                    {{-- Pengeluaran Utama Box --}}
                    <div class="p-5 rounded-2xl bg-[#FCF0ED]/60 border border-[#9D3D2B]/20 text-center">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Beban Operasional Bulan Ini</p>
                        <div class="text-3xl font-serif font-bold text-[#9D3D2B] mt-1">
                            Rp {{ number_format($thisMonthExpense ?? 0, 0, ',', '.') }}
                        </div>
                        <div class="mt-3 max-w-xs mx-auto">
                            <div class="flex justify-between text-[11px] font-bold text-gray-600 mb-1">
                                <span>Rasio Beban terhadap Omset</span>
                                <span>{{ $expenseRatio }}%</span>
                            </div>
                            <div class="w-full bg-[#EAE4DF] h-2 rounded-full overflow-hidden">
                                <div style="width: {{ max(5, $expenseRatio) }}%" class="h-full {{ $expenseRatio > 75 ? 'bg-[#9D3D2B]' : 'bg-[#4E8057]' }} rounded-full"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Kategori Terbanyak --}}
                    <div>
                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Pos Pengeluaran Terbesar</h4>
                        @if($thisMonthExpenseCategories->isEmpty())
                            <p class="text-xs text-gray-400 font-medium py-3 text-center">Belum ada pos pengeluaran tercatat bulan ini.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($thisMonthExpenseCategories as $item)
                                    <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 border border-gray-100 text-xs">
                                        <div class="flex items-center gap-2.5">
                                            <div class="size-7 rounded-lg bg-gray-200 flex items-center justify-center font-bold text-gray-600 text-[10px]">
                                                {{ substr($item->category->name ?? 'Lain', 0, 2) }}
                                            </div>
                                            <span class="font-bold text-gray-800">{{ $item->category->name ?? 'Lain-lain' }}</span>
                                        </div>
                                        <span class="font-bold text-gray-900">Rp {{ number_format($item->total, 0, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex gap-2 pt-2">
                        <a href="{{ route('sikas.transactions.index', ['type' => 'expense']) }}" class="w-full py-3 bg-[#9D3D2B] hover:bg-[#853323] text-white text-xs font-bold rounded-xl text-center shadow transition flex items-center justify-center gap-2">
                            <span>Filter Transaksi Pengeluaran</span>
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            {{-- MODAL 3: Atur Target Cuan & Periode --}}
            <div x-show="showTargetModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" style="display:none;">
                <div @click.away="showTargetModal = false" class="bg-white rounded-3xl border border-[#F2E8E5] p-6 lg:p-8 w-full max-w-lg shadow-2xl space-y-5">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="size-11 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center shrink-0">
                                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6"/></svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-serif font-bold text-gray-900">Atur Target Cuan Bisnis</h3>
                                <p class="text-xs text-gray-500 font-medium mt-0.5">Tentukan sasaran omset & periode pencapaian</p>
                            </div>
                        </div>
                        <button @click="showTargetModal = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                    </div>

                    <form id="targetCuanForm" @submit.prevent="saveTargetCuan()" action="{{ route('sikas.target_cuan.update') }}" method="POST" class="space-y-4">
                        @csrf
                        
                        {{-- Pilihan Periode Target --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Pilihan Periode Target</label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <label class="cursor-pointer">
                                    <input type="radio" name="target_cuan_period" value="monthly" x-model="targetPeriod" class="peer sr-only">
                                    <div class="py-2.5 px-2 text-center text-xs font-bold rounded-xl border border-gray-200 peer-checked:bg-[#9D3D2B] peer-checked:text-white peer-checked:border-[#9D3D2B] peer-checked:shadow-sm transition">
                                        1 Bulan
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="target_cuan_period" value="quarterly" x-model="targetPeriod" class="peer sr-only">
                                    <div class="py-2.5 px-2 text-center text-xs font-bold rounded-xl border border-gray-200 peer-checked:bg-[#9D3D2B] peer-checked:text-white peer-checked:border-[#9D3D2B] peer-checked:shadow-sm transition">
                                        3 Bulan
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="target_cuan_period" value="semester" x-model="targetPeriod" class="peer sr-only">
                                    <div class="py-2.5 px-2 text-center text-xs font-bold rounded-xl border border-gray-200 peer-checked:bg-[#9D3D2B] peer-checked:text-white peer-checked:border-[#9D3D2B] peer-checked:shadow-sm transition">
                                        6 Bulan
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="target_cuan_period" value="yearly" x-model="targetPeriod" class="peer sr-only">
                                    <div class="py-2.5 px-2 text-center text-xs font-bold rounded-xl border border-gray-200 peer-checked:bg-[#9D3D2B] peer-checked:text-white peer-checked:border-[#9D3D2B] peer-checked:shadow-sm transition">
                                        1 Tahun
                                    </div>
                                </label>
                            </div>
                        </div>

                        {{-- Input Nominal Target --}}
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">Nominal Target (Rp)</label>
                                <span class="text-xs font-bold text-[#9D3D2B]" x-text="'Rp ' + Number(targetNominal || 0).toLocaleString('id-ID')"></span>
                            </div>
                            <div class="relative">
                                <span class="absolute left-3.5 top-2.5 text-xs font-bold text-gray-400">Rp</span>
                                <input type="number" name="target_cuan" x-model="targetNominal" required min="1000" step="1000"
                                       class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-base font-bold text-gray-900 focus:outline-none focus:border-[#9D3D2B] focus:bg-white transition">
                            </div>
                        </div>

                        {{-- Quick Preset Chips --}}
                        <div>
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2">Preset Cepat Nominal:</p>
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button" @click="targetNominal = 100000" class="px-2.5 py-1 text-xs font-bold rounded-lg border border-gray-200 hover:bg-gray-100 transition">100 rb</button>
                                <button type="button" @click="targetNominal = 500000" class="px-2.5 py-1 text-xs font-bold rounded-lg border border-gray-200 hover:bg-gray-100 transition">500 rb</button>
                                <button type="button" @click="targetNominal = 1000000" class="px-2.5 py-1 text-xs font-bold rounded-lg border border-gray-200 hover:bg-gray-100 transition">1 jt</button>
                                <button type="button" @click="targetNominal = 5000000" class="px-2.5 py-1 text-xs font-bold rounded-lg border border-gray-200 hover:bg-gray-100 transition">5 jt</button>
                                <button type="button" @click="targetNominal = 10000000" class="px-2.5 py-1 text-xs font-bold rounded-lg border border-gray-200 hover:bg-gray-100 transition">10 jt</button>
                                <button type="button" @click="targetNominal = 25000000" class="px-2.5 py-1 text-xs font-bold rounded-lg border border-gray-200 hover:bg-gray-100 transition">25 jt</button>
                                <button type="button" @click="targetNominal = 50000000" class="px-2.5 py-1 text-xs font-bold rounded-lg border border-gray-200 hover:bg-gray-100 transition">50 jt</button>
                            </div>
                        </div>

                        {{-- Live Preview Progres (Realtime Reactive) --}}
                        <div class="p-4 rounded-2xl bg-[#FAF4F2] border border-[#F2E8E5]">
                            <div class="flex justify-between text-xs font-bold text-gray-700 mb-1.5">
                                <span>Pencapaian Target</span>
                                <span class="text-[#4E8057]" x-text="getDynamicProgress() + '%'"></span>
                            </div>
                            <div class="w-full bg-gray-200 h-2 rounded-full overflow-hidden">
                                <div :style="'width: ' + Math.max(4, getDynamicProgress()) + '%'"
                                     class="bg-[#4E8057] h-full rounded-full transition-all duration-300"></div>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-2">
                                Pemasukan periode <span class="font-bold text-gray-700" x-text="getPeriodLabel()"></span>: <strong class="text-gray-900" x-text="'Rp ' + formatRupiah(getCurrentPeriodIncome())"></strong>
                            </p>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="showTargetModal = false" class="flex-1 py-2.5 border border-gray-200 text-xs font-semibold rounded-xl hover:bg-gray-50">Batal</button>
                            <button type="submit" :disabled="targetSaving" class="flex-1 py-2.5 bg-[#9D3D2B] hover:bg-[#853323] text-white text-xs font-bold rounded-xl shadow transition disabled:opacity-50" x-text="targetSaving ? 'Menyimpan...' : 'Simpan Target Cuan'"></button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- AI Financial Advisor Widget --}}
            @if(!empty($aiInsight))
                <div class="bg-gradient-to-r from-[#FAF4F2] via-white to-[#FAF4F2] rounded-3xl border border-[#F2E8E5] p-6 lg:p-7 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">
                    <div class="flex items-start gap-4">
                        <div class="size-12 rounded-2xl bg-[#9D3D2B] text-white flex items-center justify-center shrink-0 shadow-md">
                            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"/></svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-serif font-bold text-gray-900">SiKas AI Financial Advisor</h3>
                                <span class="px-2.5 py-0.5 bg-[#FCF0ED] text-[#9D3D2B] text-[10px] font-bold uppercase rounded-full tracking-wider border border-[#9D3D2B]/20">Smart Insight</span>
                            </div>
                            <p class="text-sm text-gray-700 mt-1.5 leading-relaxed font-medium">{{ $aiInsight }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Stat Cards (Clean Minimalist & Clickable) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-5">
                {{-- Card 1: Total Saldo Kas --}}
                <div @click="showBalanceModal = true"
                     class="bg-[#DCE7DD] rounded-3xl p-5 lg:p-6 shadow-sm flex flex-col justify-between cursor-pointer hover:opacity-95 transition">
                    <div>
                        <p class="text-[11px] font-bold text-gray-700 uppercase tracking-widest">Total Saldo Kas</p>
                        <div class="text-xl sm:text-2xl 2xl:text-3xl font-serif font-bold text-gray-900 mt-2 leading-tight">
                            Rp {{ number_format($totalBalance ?? 0, 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 mt-4 text-xs font-semibold {{ $growthPercent >= 0 ? 'text-[#4E8057]' : 'text-[#9D3D2B]' }}">
                        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            @if($growthPercent >= 0)
                                <path d="m5 12 7-7 7 7"/><path d="M12 19V5"/>
                            @else
                                <path d="m19 12-7 7-7-7"/><path d="M12 5v14"/>
                            @endif
                        </svg>
                        <span>{{ $growthPercent >= 0 ? '+' : '' }}{{ $growthPercent }}% bln ini</span>
                    </div>
                </div>

                {{-- Card 2: Pengeluaran Bulan Ini --}}
                <div @click="showExpenseModal = true"
                     class="bg-[#EAE4DF] rounded-3xl p-5 lg:p-6 shadow-sm flex flex-col justify-between cursor-pointer hover:opacity-95 transition">
                    <div>
                        <p class="text-[11px] font-bold text-gray-700 uppercase tracking-widest">Total Pengeluaran</p>
                        <div class="text-xl sm:text-2xl 2xl:text-3xl font-serif font-bold text-gray-900 mt-2 leading-tight">
                            Rp {{ number_format($thisMonthExpense ?? 0, 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-[11px] font-semibold text-gray-600 mb-1">
                            <span>Rasio Pengeluaran</span>
                            <span>{{ $expenseRatio }}%</span>
                        </div>
                        <div class="w-full bg-[#D5CCC9] h-2 rounded-full overflow-hidden">
                            <div style="width: {{ max(5, $expenseRatio) }}%" class="h-full {{ $expenseRatio > 75 ? 'bg-[#9D3D2B]' : 'bg-[#4E8057]' }} rounded-full transition-all duration-500"></div>
                        </div>
                    </div>
                </div>

                {{-- Card 3: Target Cuan (Clean Minimalist, Fully Reactive & Clickable) --}}
                <div @click="showTargetModal = true"
                     class="bg-white border border-gray-200 rounded-3xl p-5 lg:p-6 shadow-sm flex flex-col justify-between sm:col-span-2 xl:col-span-1 cursor-pointer hover:shadow-md transition">
                    <div>
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-widest">Target Cuan</p>
                            <span class="text-[11px] font-bold text-[#4E8057]" x-text="getDynamicProgress() + '%'"></span>
                        </div>
                        <div class="text-xl sm:text-2xl 2xl:text-3xl font-serif font-bold text-gray-900 mt-2 leading-tight"
                             x-text="'Rp ' + formatRupiah(targetNominal)">
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                            <div :style="'width: ' + Math.max(4, getDynamicProgress()) + '%'" class="bg-[#9D3D2B] h-full rounded-full transition-all duration-300"></div>
                        </div>
                        <p class="text-xs text-gray-400 font-medium mt-2" x-text="'Batas waktu: ' + getDeadlineLabel()"></p>
                    </div>
                </div>
            </div>

            {{-- Chart Section --}}
            <div class="bg-white rounded-3xl border border-gray-200 p-8 shadow-sm">
                <div class="flex justify-between items-start mb-10">
                    <div>
                        <h2 class="text-[22px] font-serif font-bold text-gray-900">Tren Pendapatan & Pengeluaran</h2>
                        <p class="text-sm text-gray-500 mt-1">7 hari terakhir</p>
                    </div>
                    <div class="flex items-center gap-6 text-xs font-bold text-gray-700 tracking-wider">
                        <div class="flex items-center gap-2">
                            <div class="size-2.5 rounded-full bg-[#6A8B78]"></div>
                            MASUK
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="size-2.5 rounded-full bg-[#9D3D2B]"></div>
                            KELUAR
                        </div>
                    </div>
                </div>
                
                {{-- Real Chart.js canvas --}}
                <div class="relative w-full h-48">
                    <canvas id="trendChart"></canvas>
                </div>
                
                {{-- X Axis Labels from real data --}}
                <div class="flex justify-between items-center text-[11px] font-bold text-gray-400 mt-4 px-2 tracking-widest">
                    @foreach($chartData as $point)
                        <span>{{ strtoupper($point['date']) }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
        <script>
            const chartData = @json($chartData);
            const labels = chartData.map(d => d.date);
            const incomeData = chartData.map(d => d.income);
            const expenseData = chartData.map(d => d.expense);

            new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Masuk',
                            data: incomeData,
                            borderColor: '#6A8B78',
                            backgroundColor: 'rgba(106,139,120,0.1)',
                            borderWidth: 2.5,
                            pointBackgroundColor: '#6A8B78',
                            pointRadius: 4,
                            fill: true,
                            tension: 0.4,
                        },
                        {
                            label: 'Keluar',
                            data: expenseData,
                            borderColor: '#9D3D2B',
                            backgroundColor: 'rgba(157,61,43,0.07)',
                            borderWidth: 2.5,
                            pointBackgroundColor: '#9D3D2B',
                            pointRadius: 4,
                            fill: true,
                            tension: 0.4,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { display: false },
                        y: {
                            display: true,
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: {
                                callback: (v) => v === 0 ? '0' : (v/1000000 >= 1 ? v/1000000+'jt' : v/1000+'rb'),
                                font: { size: 10 }
                            }
                        }
                    }
                }
            });
        </script>
        @endpush

        {{-- Right Sidebar with Client-side Instant Search --}}
        <div x-data="{ quickSearch: '' }" class="w-[300px] xl:w-[340px] shrink-0 bg-[#FAF4F2] border-l border-[#F2E8E5] flex flex-col h-full overflow-y-auto hidden lg:flex">
            <div class="p-8 flex-1">
                <h2 class="text-[22px] font-serif font-bold text-gray-900 mb-6">Riwayat Cepat</h2>
                
                {{-- Search Bar --}}
                <div class="relative mb-8">
                    <svg class="absolute left-4 top-3.5 size-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" x-model="quickSearch" placeholder="Cari transaksi..." class="w-full bg-white border border-gray-200 rounded-full py-3 pl-11 pr-4 text-sm focus:outline-none focus:border-[#9D3D2B] shadow-sm">
                </div>

                {{-- HARI INI (Real Data) --}}
                <div class="mb-8">
                    <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-widest mb-4">Hari Ini</h3>
                    <div class="space-y-4">
                        @forelse($todayTransactions as $tx)
                            <div x-show="!quickSearch || '{{ strtolower(addslashes($tx->description ?? '')) }}'.includes(quickSearch.toLowerCase())"
                                 class="flex items-start justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="size-10 rounded-full {{ $tx->type === 'income' ? 'bg-[#DCE7DD] text-[#4E8057]' : 'bg-[#EAE4DF] text-gray-600' }} flex items-center justify-center shrink-0">
                                        @if($tx->type === 'income')
                                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                                        @else
                                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-[14px] font-bold text-gray-900 leading-tight">{{ $tx->description ?? 'Transaksi' }}</p>
                                        <p class="text-[12px] text-gray-500 mt-1 font-medium">{{ $tx->transaction_date ? $tx->transaction_date->format('H:i') . ' WIB' : '' }} • {{ ucfirst($tx->payment_method ?? 'cash') }}</p>
                                    </div>
                                </div>
                                <span class="text-[14px] font-bold {{ $tx->type === 'income' ? 'text-[#4E8057]' : 'text-[#9D3D2B]' }} shrink-0 ml-2">
                                    {{ $tx->type === 'income' ? '+' : '-' }} Rp {{ number_format($tx->amount, 0, ',', '.') }}
                                </span>
                            </div>
                        @empty
                            <p class="text-[13px] text-gray-400 font-medium">Belum ada transaksi hari ini.</p>
                        @endforelse
                    </div>
                </div>

                {{-- KEMARIN (Real Data) --}}
                <div class="mb-8">
                    <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-widest mb-4">Kemarin</h3>
                    <div class="space-y-4">
                        @forelse($yesterdayTransactions as $tx)
                            <div x-show="!quickSearch || '{{ strtolower(addslashes($tx->description ?? '')) }}'.includes(quickSearch.toLowerCase())"
                                 class="flex items-start justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="size-10 rounded-full {{ $tx->type === 'income' ? 'bg-[#DCE7DD] text-[#4E8057]' : 'bg-[#EAE4DF] text-gray-600' }} flex items-center justify-center shrink-0">
                                        @if($tx->type === 'income')
                                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                                        @else
                                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-[14px] font-bold text-gray-900 leading-tight">{{ $tx->description ?? 'Transaksi' }}</p>
                                        <p class="text-[12px] text-gray-500 mt-1 font-medium">{{ $tx->transaction_date ? $tx->transaction_date->format('H:i') . ' WIB' : '' }} • {{ ucfirst($tx->payment_method ?? 'cash') }}</p>
                                    </div>
                                </div>
                                <span class="text-[14px] font-bold {{ $tx->type === 'income' ? 'text-[#4E8057]' : 'text-[#9D3D2B]' }} shrink-0 ml-2">
                                    {{ $tx->type === 'income' ? '+' : '-' }} Rp {{ number_format($tx->amount, 0, ',', '.') }}
                                </span>
                            </div>
                        @empty
                            <p class="text-[13px] text-gray-400 font-medium">Tidak ada transaksi kemarin.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Footer Button --}}
            <div class="p-8 border-t border-[#F2E8E5] bg-white/30">
                <a href="{{ route('sikas.transactions.index') }}"
                   class="w-full py-3.5 border-2 border-dashed border-[#D5CCC9] rounded-xl text-[13px] font-bold text-gray-600 hover:bg-white hover:border-gray-300 transition-all flex justify-center items-center gap-2">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    Lihat Semua Riwayat
                </a>
            </div>
        </div>

    </div>

    @push('scripts')
    <script>
        function voiceInput() {
            const categories = @json($categories);

            return {
                recording: false,
                loading: false,
                showConfirm: false,
                aiResult: { description: '', amount: '', type: 'income', category_id: '', category: '' },
                statusMessage: '"Tap mikrofon, bicara, lalu konfirmasi transaksimu."',
                mediaRecorder: null,
                audioChunks: [],

                async toggleRecording() {
                    if (this.loading) return;

                    if (this.recording) {
                        this.mediaRecorder.stop();
                        this.recording = false;
                        this.statusMessage = 'Memproses rekaman...';
                    } else {
                        try {
                            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                            this.audioChunks = [];
                            this.mediaRecorder = new MediaRecorder(stream);

                            this.mediaRecorder.ondataavailable = (event) => {
                                if (event.data.size > 0) this.audioChunks.push(event.data);
                            };

                            this.mediaRecorder.onstop = async () => {
                                stream.getTracks().forEach(t => t.stop());
                                await this.processAudio();
                            };

                            this.mediaRecorder.start();
                            this.recording = true;
                            this.statusMessage = 'Merekam... Tekan tombol lagi untuk berhenti.';
                        } catch (err) {
                            this.statusMessage = 'Akses mikrofon ditolak. Periksa izin browser Anda.';
                        }
                    }
                },

                async processAudio() {
                    this.loading = true;
                    const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
                    const formData = new FormData();
                    formData.append('audio', audioBlob, 'voice.webm');
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                    try {
                        const response = await fetch('{{ route("sikas.voice") }}', {
                            method: 'POST',
                            body: formData,
                        });
                        const json = await response.json();

                        if (json.status === 'success') {
                            const data = json.data || {};
                            // Auto match category to category_id
                            let matchedCat = null;
                            if (data.category) {
                                const catQuery = data.category.toLowerCase();
                                matchedCat = categories.find(c => c.name.toLowerCase().includes(catQuery) || catQuery.includes(c.name.toLowerCase()));
                            }
                            if (!matchedCat && data.type) {
                                matchedCat = categories.find(c => c.type === data.type);
                            }

                            this.aiResult = {
                                description: data.description || '',
                                amount: data.amount || 0,
                                type: data.type || 'income',
                                category: data.category || '',
                                category_id: matchedCat ? matchedCat.id : ''
                            };
                            this.showConfirm = true;
                            this.statusMessage = 'AI berhasil menganalisis rekaman. Konfirmasi untuk menyimpan.';
                        } else {
                            this.statusMessage = json.message || 'Gagal memproses suara. Coba lagi.';
                        }
                    } catch (err) {
                        this.statusMessage = 'Gagal menghubungi server. Periksa koneksi internet.';
                    } finally {
                        this.loading = false;
                    }
                },

                async submitVoiceTransaction(event) {
                    const form = event.target;
                    const formData = new FormData(form);
                    this.showConfirm = false; // Smoothly close confirm modal immediately
                    window.dispatchEvent(new CustomEvent('notify-toast', { detail: { message: 'Menyimpan transaksi suara...', type: 'info' } }));

                    try {
                        const res = await fetch('{{ route("sikas.transactions.store") }}', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            window.dispatchEvent(new CustomEvent('notify-toast', { detail: { message: 'Transaksi suara berhasil dicatat!', type: 'success' } }));
                            setTimeout(() => { window.location.reload(); }, 500);
                        } else {
                            window.dispatchEvent(new CustomEvent('notify-toast', { detail: { message: json.message || 'Gagal mencatat transaksi.', type: 'error' } }));
                        }
                    } catch (err) {
                        window.dispatchEvent(new CustomEvent('notify-toast', { detail: { message: 'Terjadi kesalahan sistem.', type: 'error' } }));
                    }
                }
            };
        }

        function sikasDashboard() {
            return {
                showManualModal: false,
                showCategoryModal: false,
                showBalanceModal: false,
                showExpenseModal: false,
                showTargetModal: false,
                targetSaving: false,
                toastMessage: '',
                toastType: 'success',
                showToast: false,
                targetNominal: {{ (int) $targetNominal }},
                targetPeriod: '{{ $targetPeriod }}',
                periodIncomes: @json($periodIncomes),
                allCategories: @json($categories),
                newCatName: '',
                newCatType: 'expense',
                formatRupiah(num) {
                    return Number(num || 0).toLocaleString('id-ID');
                },
                getCurrentPeriodIncome() {
                    return Number(this.periodIncomes[this.targetPeriod] || 0);
                },
                getDynamicProgress() {
                    const nom = Number(this.targetNominal) || 1;
                    const inc = this.getCurrentPeriodIncome();
                    return Math.min(100, Math.round((inc / nom) * 100));
                },
                getPeriodLabel() {
                    if (this.targetPeriod === 'quarterly') return '3 Bulan';
                    if (this.targetPeriod === 'semester') return '6 Bulan';
                    if (this.targetPeriod === 'yearly') return '1 Tahun';
                    return '1 Bulan';
                },
                getDeadlineLabel() {
                    if (this.targetPeriod === 'quarterly') return 'Kuartal ' + Math.ceil({{ now()->month }} / 3) + ' {{ now()->year }}';
                    if (this.targetPeriod === 'semester') return 'Semester ' + ({{ now()->month }} <= 6 ? '1' : '2') + ' {{ now()->year }}';
                    if (this.targetPeriod === 'yearly') return 'Tahun {{ now()->year }}';
                    return '{{ now()->locale("id")->isoFormat("MMMM Y") }}';
                },
                showNotification(msg, type = 'success') {
                    this.toastMessage = msg;
                    this.toastType = type;
                    this.showToast = true;
                    if (type !== 'info') {
                        setTimeout(() => { this.showToast = false; }, 3500);
                    }
                },
                async saveManualTx(event) {
                    const form = event.target;
                    const formData = new FormData(form);
                    this.showManualModal = false;
                    this.showNotification('Menyimpan transaksi...', 'info');

                    try {
                        const res = await fetch('{{ route("sikas.transactions.store") }}', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            this.showNotification('Transaksi berhasil dicatat!', 'success');
                            form.reset();
                            setTimeout(() => { window.location.reload(); }, 500);
                        } else {
                            this.showNotification(json.message || 'Gagal mencatat transaksi.', 'error');
                        }
                    } catch (err) {
                        this.showNotification('Terjadi kesalahan saat menyimpan.', 'error');
                    }
                },
                async saveQuickCategory(event) {
                    const form = event.target;
                    const formData = new FormData(form);
                    this.showCategoryModal = false;
                    this.showNotification('Menambahkan kategori...', 'info');

                    try {
                        const res = await fetch('{{ route("sikas.categories.store") }}', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            this.showNotification('Kategori baru berhasil ditambahkan!', 'success');
                            if (json.data) {
                                this.allCategories.push(json.data);
                            }
                            form.reset();
                        } else {
                            this.showNotification(json.message || 'Gagal menambahkan kategori.', 'error');
                        }
                    } catch (err) {
                        this.showNotification('Terjadi kesalahan sistem.', 'error');
                    }
                },
                async saveTargetCuan() {
                    this.targetSaving = true;
                    try {
                        const res = await fetch('{{ route("sikas.target_cuan.update") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                target_cuan: this.targetNominal,
                                target_cuan_period: this.targetPeriod
                            })
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            this.showTargetModal = false;
                            this.showNotification('Target cuan berhasil diperbarui!', 'success');
                        }
                    } catch (err) {
                        document.getElementById('targetCuanForm')?.submit();
                    } finally {
                        this.targetSaving = false;
                    }
                }
            };
        }
    </script>
    @endpush
</x-layouts.app>
