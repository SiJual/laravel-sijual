<x-layouts.app title="SiPromo — Pemasaran AI" activeNav="sipromo">
    <div x-data="{
        prompt: '',
        contentType: 'social_media',
        suggest(text) { this.prompt = text; }
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

        {{-- Prompt Input Form --}}
        <form action="{{ route('sipromo.generate.create') }}" method="POST" class="bg-surface p-6 rounded-lg border border-border shadow-card space-y-4">
            @csrf
            <div>
                <label for="prompt" class="block text-xs font-semibold text-on-surface mb-2">Topik atau Ide Promosi Anda</label>
                <textarea id="prompt" name="prompt" x-model="prompt" rows="3" required
                          placeholder="Contoh: Diskon 20% Kopi Kenangan Susu Gula Aren edisi akhir pekan di Kebayoran Baru..."
                          class="w-full px-4 py-3 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 outline-none"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1.5">Jenis Konten</label>
                    <select name="content_type" x-model="contentType" class="w-full px-3.5 py-2.5 bg-surface-alt border border-border-input rounded-md text-sm text-on-surface focus:ring-2 focus:ring-primary/20 outline-none">
                        <option value="social_media">Social Media (Instagram / FB)</option>
                        <option value="ad_copy">Ad Copy (Iklan Digital)</option>
                        <option value="blog_post">Blog Post / Artikel</option>
                        <option value="email">Email Marketing / WhatsApp Broadcast</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1.5">Model AI Generatif</label>
                    <input type="text" value="Flux Schnell + Gemini 2.0 Flash" disabled class="w-full px-3.5 py-2.5 bg-surface-alt/60 border border-border-input rounded-md text-sm text-on-surface-variant cursor-not-allowed">
                </div>
            </div>

            {{-- Suggestion Chips --}}
            <div class="space-y-2 pt-2">
                <span class="text-xs font-semibold text-on-surface-variant">Saran Ide Prompt Cepat:</span>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="suggest('Promo diskon 20% kopi gula aren spesial akhir pekan')" class="px-3 py-1 bg-surface-alt hover:bg-surface-warm text-xs font-semibold text-on-surface rounded-full border border-border-input">
                        ☕ Diskon Kopi Akhir Pekan
                    </button>
                    <button type="button" @click="suggest('Peluncuran menu makanan baru dengan bahan berkualitas lokal')" class="px-3 py-1 bg-surface-alt hover:bg-surface-warm text-xs font-semibold text-on-surface rounded-full border border-border-input">
                        🍲 Menu Makanan Baru
                    </button>
                    <button type="button" @click="suggest('Ucapan terima kasih pelanggan dan giveaway mingguan')" class="px-3 py-1 bg-surface-alt hover:bg-surface-warm text-xs font-semibold text-on-surface rounded-full border border-border-input">
                        🎁 Giveaway Pelanggan
                    </button>
                </div>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" class="px-6 py-3 bg-primary text-white font-semibold text-sm rounded-md shadow-card hover:bg-primary/90 transition flex items-center gap-2">
                    <span>✨ Generate Konten Promosi</span>
                </button>
            </div>
        </form>

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
                            :type="$item->content_type"
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
