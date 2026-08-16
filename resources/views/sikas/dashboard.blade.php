<x-layouts.app title="SiKas — Keuangan Pintar" activeNav="sikas" :hideTopBar="true">
    <div class="flex h-full w-full">
        
        {{-- Main Content Column --}}
        <div class="flex-1 overflow-y-auto p-6 lg:p-10 flex flex-col gap-8">
            
            {{-- Header --}}
            <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                <div>
                    <h1 class="text-3xl lg:text-4xl font-serif font-bold text-gray-900 tracking-tight">Dasbor Keuangan</h1>
                    <p class="text-sm lg:text-base text-gray-500 font-medium mt-1 lg:mt-2">Selamat pagi, Rahardian. Mari kelola arus kas Anda hari ini.</p>
                </div>
                <div class="flex items-center gap-4 shrink-0 self-end sm:self-auto">
                    <button class="size-11 rounded-full border border-[#D5CCC9] flex items-center justify-center text-gray-500 hover:bg-white transition-colors">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </button>
                    <div class="size-11 rounded-full border-2 border-[#9D3D2B] bg-white overflow-hidden shadow-sm">
                        {{-- Avatar placeholder --}}
                        <div class="w-full h-full bg-[#EAD6D1]"></div>
                    </div>
                </div>
            </div>

            {{-- Voice Input Card --}}
            <div x-data="voiceInput()" class="bg-white rounded-[24px] border border-[#F2E8E5] p-6 lg:p-8 flex flex-col sm:flex-row justify-between items-center sm:items-start lg:items-center relative overflow-hidden gap-10">
                
                {{-- Background decorative vertical bars --}}
                <div class="absolute right-6 lg:right-16 top-0 flex gap-4 opacity-50 z-0">
                    <div class="w-5 h-16 bg-gray-100"></div>
                    <div class="w-5 h-28 bg-gray-100"></div>
                    <div class="w-5 h-40 bg-gray-100"></div>
                </div>

                <div class="flex-1 max-w-xl relative z-10">
                    <h2 class="text-2xl lg:text-[28px] font-serif font-bold text-gray-900 tracking-tight">Input Suara Pintar</h2>
                    <p class="text-base lg:text-[17px] text-[#6B5A57] font-medium leading-relaxed mt-3 lg:mt-4" x-text="statusMessage">
                        "Tadi makan siang soto lamongan habis lima puluh ribu rupiah dari dompet."
                    </p>
                    <div class="flex items-center gap-4 mt-6 lg:mt-8">
                        <div class="w-8 h-[3px] rounded-full bg-[#BD5B43] shrink-0"></div>
                        <span class="text-[#BD5B43] font-bold text-xs lg:text-sm tracking-widest uppercase">Catat Transaksi Suara</span>
                    </div>
                </div>
                
                {{-- Voice Button Area --}}
                <div class="relative z-10 shrink-0 w-32 h-32 flex items-center justify-center sm:mr-4 lg:mr-10">
                    <div class="absolute size-36 border border-[#F2E8E5] rounded-full" :class="{'animate-ping opacity-30': recording}"></div>
                    <div class="absolute size-[116px] border border-[#F2E8E5] rounded-full"></div>
                    
                    <button @click="toggleRecording()"
                            class="relative size-[76px] rounded-full flex items-center justify-center text-white shadow-[0_0_0_6px_white,0_4px_12px_rgba(0,0,0,0.1)] hover:scale-105 transition-transform z-10"
                            :class="recording ? 'bg-red-600' : 'bg-[#BD5B43]'"
                            :disabled="loading">
                        <template x-if="!loading">
                            <svg class="size-7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <template x-if="!recording"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></template>
                                <template x-if="recording"><rect width="18" height="18" x="3" y="3" rx="2"/></template>
                            </svg>
                        </template>
                        <template x-if="loading">
                            <svg class="size-6 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                        </template>
                    </button>
                </div>

                {{-- Confirmation Modal --}}
                <div x-show="showConfirm" x-transition class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40 backdrop-blur-sm" style="display:none;">
                    <div @click.away="showConfirm = false" class="bg-white rounded-3xl border border-[#F2E8E5] p-6 w-full max-w-md shadow-2xl space-y-5">
                        <div>
                            <h3 class="text-lg font-serif font-bold text-gray-900">Konfirmasi Transaksi AI</h3>
                            <p class="text-sm text-gray-500 mt-1">Periksa dan konfirmasi hasil analisis suara berikut:</p>
                        </div>
                        <div class="bg-[#FAF4F2] rounded-xl p-4 border border-[#F2E8E5] space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Deskripsi</span>
                                <span class="font-semibold text-gray-900" x-text="aiResult?.description"></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Jumlah</span>
                                <span class="font-semibold text-gray-900">Rp <span x-text="Number(aiResult?.amount || 0).toLocaleString('id-ID')"></span></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Kategori</span>
                                <span class="font-semibold text-gray-900" x-text="aiResult?.category"></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Tipe</span>
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold" :class="aiResult?.type === 'income' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" x-text="aiResult?.type === 'income' ? 'Pemasukan' : 'Pengeluaran'"></span>
                            </div>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button @click="showConfirm = false" class="flex-1 py-2.5 border border-gray-200 text-gray-600 font-semibold text-sm rounded-xl hover:bg-gray-50 transition">
                                Batal
                            </button>
                            <form :action="'{{ route('sikas.transactions.store') }}'" method="POST" class="flex-1">
                                @csrf
                                <input type="hidden" name="type" :value="aiResult?.type">
                                <input type="hidden" name="amount" :value="aiResult?.amount">
                                <input type="hidden" name="description" :value="aiResult?.description">
                                <input type="hidden" name="transaction_date" :value="new Date().toISOString().split('T')[0]">
                                <input type="hidden" name="source" value="voice">
                                <button type="submit" class="w-full py-2.5 bg-[#9D3D2B] text-white font-semibold text-sm rounded-xl hover:bg-[#9D3D2B]/90 transition">
                                    Simpan Transaksi
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Stat Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
                {{-- Card 1 --}}
                <div class="bg-[#DCE7DD] rounded-3xl p-5 lg:p-7 shadow-sm">
                    <p class="text-[10px] lg:text-[11px] font-bold text-gray-700 uppercase tracking-widest">Total Saldo</p>
                    <h3 class="text-2xl lg:text-3xl font-serif font-bold text-gray-900 mt-2 lg:mt-3 truncate">@rupiahShort($totalBalance ?? 42500000)</h3>
                    <div class="flex items-center gap-1.5 mt-4 lg:mt-6 text-xs lg:text-sm text-[#4E8057] font-semibold">
                        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m5 12 7-7 7 7"/><path d="M12 19V5"/></svg>
                        <span class="truncate">+2.4% bln ini</span>
                    </div>
                </div>

                {{-- Card 2 --}}
                <div class="bg-[#EAE4DF] rounded-3xl p-5 lg:p-7 shadow-sm">
                    <p class="text-[10px] lg:text-[11px] font-bold text-gray-700 uppercase tracking-widest">Penghematan</p>
                    <h3 class="text-2xl lg:text-3xl font-serif font-bold text-gray-900 mt-2 lg:mt-3 truncate">@rupiahShort($totalIncome ?? 8200000)</h3>
                    <div class="mt-6 lg:mt-8 flex gap-1 h-1.5 bg-[#D5CCC9] rounded-full overflow-hidden">
                        <div class="w-[60%] h-full bg-[#4E8057] rounded-full"></div>
                        <div class="w-[20%] h-full bg-[#EAD6D1] rounded-full"></div>
                    </div>
                </div>

                {{-- Card 3 --}}
                <div class="bg-white border border-gray-200 rounded-3xl p-5 lg:p-7 shadow-sm">
                    <p class="text-[10px] lg:text-[11px] font-bold text-gray-500 uppercase tracking-widest">Target Cuan</p>
                    <h3 class="text-2xl lg:text-3xl font-serif font-bold text-gray-900 mt-2 lg:mt-3 truncate">@rupiahShort(100000000)</h3>
                    <p class="text-xs text-gray-400 font-medium mt-5 lg:mt-7 truncate">Batas waktu: Des 2024</p>
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

        {{-- Right Sidebar --}}
        <div class="w-[300px] xl:w-[340px] shrink-0 bg-[#FAF4F2] border-l border-[#F2E8E5] flex flex-col h-full overflow-y-auto hidden lg:flex">
            <div class="p-8 flex-1">
                <h2 class="text-[22px] font-serif font-bold text-gray-900 mb-6">Riwayat Cepat</h2>
                
                {{-- Search Bar --}}
                <div class="relative mb-8">
                    <svg class="absolute left-4 top-3.5 size-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" placeholder="Cari transaksi..." class="w-full bg-white border border-gray-200 rounded-full py-3 pl-11 pr-4 text-sm focus:outline-none focus:border-[#9D3D2B] shadow-sm">
                </div>

                {{-- HARI INI (Real Data) --}}
                <div class="mb-8">
                    <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-widest mb-4">Hari Ini</h3>
                    <div class="space-y-4">
                        @forelse($todayTransactions as $tx)
                            <div class="flex items-start justify-between">
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
                                        <p class="text-[12px] text-gray-500 mt-1 font-medium">{{ $tx->transaction_date->format('H:i') }} • {{ ucfirst($tx->payment_method ?? 'cash') }}</p>
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
                            <div class="flex items-start justify-between">
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
                                        <p class="text-[12px] text-gray-500 mt-1 font-medium">{{ $tx->transaction_date->format('H:i') }} • {{ ucfirst($tx->payment_method ?? 'cash') }}</p>
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

                {{-- Promo Banner --}}
                <div class="bg-[#EAE4DF] rounded-2xl p-5 border border-[#D5CCC9]/50 shadow-sm mt-8">
                    <p class="text-[10px] font-bold text-gray-600 uppercase tracking-widest mb-1.5">Tip SiKas</p>
                    <h4 class="text-[14px] font-bold text-gray-900">Rekam suara lalu konfirmasi AI untuk catat transaksi lebih cepat!</h4>
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
            return {
                recording: false,
                loading: false,
                showConfirm: false,
                aiResult: null,
                statusMessage: '"Tap mikrofon, bicara, lalu konfirmasi transaksimu."',
                mediaRecorder: null,
                audioChunks: [],

                async toggleRecording() {
                    if (this.loading) return;

                    if (this.recording) {
                        // Stop recording and process
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
                            this.aiResult = json.data;
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
                }
            };
        }
    </script>
    @endpush
</x-layouts.app>
