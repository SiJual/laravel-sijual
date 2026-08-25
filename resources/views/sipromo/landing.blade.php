<x-layouts.app title="SiPromo — Pemasaran AI" activeNav="sipromo">
    <div x-data="{
        formOpen: {{ $errors->any() ? 'true' : 'false' }},
        prompt: '{{ addslashes(old('prompt', '')) }}',
        contentType: '{{ old('content_type', 'social_media') }}',
        submitting: false,
        elapsed: 0,
        timer: null,
        suggest(text) { this.prompt = text; this.formOpen = true; },
        start() {
            this.submitting = true;
            this.elapsed = 0;
            this.timer = setInterval(() => this.elapsed++, 1000);
        }
    }" class="p-6 lg:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="px-3 py-1 rounded-full bg-primary-light text-primary text-xs font-bold uppercase tracking-wider">
                    SiPromo AI: Generative Marketing
                </span>
                <h1 class="text-3xl font-bold font-display text-on-surface mt-2">Selamat Datang di SiPromo</h1>
                <p class="text-sm text-on-surface-variant font-medium">Buat iklan, teks promosi, dan desain produk profesional otomatis dengan AI.</p>
            </div>
            <a href="{{ route('sipromo.history') }}" class="text-sm font-semibold text-primary hover:underline">Riwayat Konten →</a>
        </div>

        @if(session('success'))
            <div class="p-4 rounded-md bg-success-bg text-success text-sm font-semibold">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-md bg-error/10 text-error text-sm font-semibold">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Prompt Input Form (collapsed by default; opens on click) --}}
        <div class="bg-surface rounded-lg border border-border shadow-card">
            <button type="button" @click="formOpen = !formOpen"
                    class="w-full flex items-center justify-between gap-4 p-5 text-left">
                <div>
                    <h3 class="text-sm font-bold text-on-surface">✨ Buat Ide Promosi Baru</h3>
                    <p class="text-xs text-on-surface-variant mt-0.5">Klik untuk membuka form dan generate konten dengan AI.</p>
                </div>
                <svg class="size-5 text-on-surface-variant shrink-0 transition-transform duration-200" :class="formOpen ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="m6 9 6 6 6-6"/>
                </svg>
            </button>

            <form x-show="formOpen" x-cloak x-transition.duration.150ms
                  action="{{ route('sipromo.generate.create') }}" method="POST" @submit="start()"
                  class="px-6 pb-6 pt-1 border-t border-border space-y-4">
            @csrf
            <div>
                <label for="prompt" class="block text-xs font-semibold text-on-surface mb-1">Topik atau Ide Promosi Anda</label>
                <p class="text-[11px] text-on-surface-variant mb-2">
                    Konten digrounding pada data SiStok. Hindari menyebut diskon atau angka yang belum tercatat — klaim tanpa bukti akan ditolak otomatis.
                </p>
                <textarea id="prompt" name="prompt" x-model="prompt" rows="3" required
                          placeholder="Contoh: Perkenalkan koleksi batik tulis sogan untuk pelanggan yang mencari kain formal..."
                          class="w-full px-4 py-3 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 outline-none"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-on-surface mb-1.5">Jenis Konten</label>
                <select name="content_type" x-model="contentType" class="w-full sm:w-1/2 px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 outline-none">
                    <option value="social_media">Social Media (Instagram / FB)</option>
                    <option value="ad_copy">Ad Copy (Iklan Digital)</option>
                    <option value="blog_post">Blog Post / Artikel</option>
                    <option value="email">Email Marketing / WhatsApp Broadcast</option>
                </select>
            </div>

            {{-- Grounding: the pipeline needs real catalogue rows --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-semibold text-on-surface">Produk yang Dipromosikan</label>
                    <span class="text-[11px] text-on-surface-variant">dari SiStok</span>
                </div>

                @if($products->isEmpty())
                    <p class="text-xs text-on-surface-variant">
                        Belum ada produk di SiStok.
                        <a href="{{ route('sistok.products.index') }}" class="font-semibold text-primary hover:underline">Tambah produk</a>
                        agar konten bisa digrounding pada data asli.
                    </p>
                @else
                    <div class="max-h-40 overflow-y-auto border border-border-input rounded-md divide-y divide-border">
                        @foreach($products as $prod)
                            <label class="flex items-center gap-3 px-3 py-2 hover:bg-surface-alt cursor-pointer">
                                <input type="checkbox" name="product_ids[]" value="{{ $prod->id }}"
                                       @checked(in_array($prod->id, old('product_ids', [])))
                                       class="rounded border-border-input text-primary focus:ring-primary/30">
                                <span class="text-sm text-on-surface flex-1">{{ $prod->name }}</span>
                                <span class="text-xs text-on-surface-variant">Rp {{ number_format($prod->price, 0, ',', '.') }} &middot; stok {{ $prod->stock_level }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-on-surface-variant mt-1.5">
                        Kosongkan untuk membiarkan sistem memilih produk yang disebut di prompt.
                    </p>
                @endif
            </div>

            {{-- Suggestion Chips --}}
            <div class="space-y-2 pt-2">
                <span class="text-xs font-semibold text-on-surface-variant">Saran Ide Prompt Cepat:</span>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="suggest('Perkenalkan produk ini kepada pelanggan baru, jelaskan bahan dan proses pembuatannya')" class="px-3 py-1 bg-surface-alt hover:bg-surface-warm text-xs font-semibold text-on-surface rounded-full border border-border-input">
                        🧵 Kenalkan Produk
                    </button>
                    <button type="button" @click="suggest('Ceritakan proses pembuatan produk ini dan siapa yang mengerjakannya')" class="px-3 py-1 bg-surface-alt hover:bg-surface-warm text-xs font-semibold text-on-surface rounded-full border border-border-input">
                        🎥 Cerita di Balik Produk
                    </button>
                    <button type="button" @click="suggest('Ajak pelanggan mampir ke toko dan lihat koleksi langsung')" class="px-3 py-1 bg-surface-alt hover:bg-surface-warm text-xs font-semibold text-on-surface rounded-full border border-border-input">
                        🏪 Ajak Mampir ke Toko
                    </button>
                </div>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" :disabled="submitting"
                        class="px-6 py-3 bg-primary text-white font-semibold text-sm rounded-md shadow-card hover:bg-primary/90 transition flex items-center gap-2 disabled:opacity-70 disabled:cursor-wait">
                    <template x-if="submitting">
                        <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                    </template>
                    <span x-show="!submitting">✨ Generate Konten Promosi</span>
                    <span x-show="submitting" x-cloak x-text="'Pipeline AI sedang menyusun draft... ' + elapsed + 's'"></span>
                </button>
            </div>
            </form>
        </div>

        {{-- Recent Generations Carousel / Grid --}}
        <div class="space-y-4">
            <h3 class="text-lg font-bold font-display text-on-surface">Hasil Generasi Terakhir</h3>
            @if($recentGenerations->isEmpty())
                <div class="p-8 bg-surface rounded-lg border border-border text-center text-on-surface-variant text-sm">
                    Belum ada konten promosi. Masukkan ide di atas dan klik Generate!
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($recentGenerations as $item)
                        <x-ui.content-preview-card
                            :title="$item->title"
                            :type="$item->content_type_label"
                            :imageUrl="$item->generated_image_url"
                            :caption="$item->caption"
                            :href="route('sipromo.preview', $item->id)"
                        />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
