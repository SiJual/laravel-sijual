<x-layouts.app title="Pratinjau Konten — SiPromo" activeNav="sipromo">
    <div x-data="{ lightbox: false, confirmDelete: false }" class="p-6 lg:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold font-display text-on-surface">{{ $content->title }}</h1>
                <p class="text-sm text-on-surface-variant font-medium">Pratinjau promosi AI generatif dan ekspor publikasi.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('sipromo.landing') }}" class="px-4 py-2 bg-surface-alt text-on-surface text-sm font-semibold rounded-md border border-border-input hover:bg-surface-warm">← Kembali</a>
                <button onclick="navigator.clipboard.writeText('{{ addslashes($content->caption) }}'); alert('Caption berhasil disalin!')" type="button" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-md shadow-sm hover:bg-primary/90">
                    📋 Salin Caption
                </button>
                <button @click="confirmDelete = true" type="button" class="px-4 py-2 bg-error/10 text-error text-sm font-semibold rounded-md border border-error/20 hover:bg-error/20">
                    🗑️ Hapus
                </button>
            </div>
        </div>

        {{-- Delete confirmation --}}
        <div x-show="confirmDelete" x-cloak x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/50 backdrop-blur-sm"
             @keydown.escape.window="confirmDelete = false">
            <div @click.away="confirmDelete = false" class="bg-surface rounded-xl border border-border p-6 w-full max-w-sm shadow-hero space-y-4">
                <div>
                    <h3 class="text-lg font-bold font-display text-on-surface">Hapus konten ini?</h3>
                    <p class="text-sm text-on-surface-variant mt-1.5">
                        "{{ $content->title }}" akan dihapus permanen, termasuk caption, hashtag, dan gambar posternya. Tindakan ini tidak bisa dibatalkan.
                    </p>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button @click="confirmDelete = false" type="button" class="px-4 py-2 bg-surface-alt text-on-surface text-sm font-semibold rounded-md border border-border-input hover:bg-surface-warm">
                        Batal
                    </button>
                    <form action="{{ route('sipromo.destroy', $content->id) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 bg-error text-white text-sm font-semibold rounded-md shadow-sm hover:bg-error/90">
                            Ya, Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Image Preview (2/3) --}}
            <div class="lg:col-span-2 space-y-4">
                @if($content->generated_image_url)
                    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-card p-4">
                        <button type="button" @click="lightbox = true"
                                class="block w-full max-h-64 bg-surface-alt rounded-lg overflow-hidden cursor-zoom-in group relative">
                            <img src="{{ $content->generated_image_url }}" alt="Preview Iklan"
                                 class="w-full max-h-64 object-contain mx-auto">
                            <span class="absolute inset-0 bg-on-surface/0 group-hover:bg-on-surface/10 transition flex items-center justify-center">
                                <span class="opacity-0 group-hover:opacity-100 transition px-3 py-1.5 rounded-full bg-on-surface/70 text-white text-xs font-semibold">
                                    🔍 Lihat gambar penuh
                                </span>
                            </span>
                        </button>
                    </div>

                    {{-- Lightbox --}}
                    <div x-show="lightbox" x-cloak x-transition.opacity
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/80 backdrop-blur-sm"
                         @keydown.escape.window="lightbox = false">
                        <div @click.away="lightbox = false" class="relative max-w-3xl w-full">
                            <button @click="lightbox = false" type="button"
                                    class="absolute -top-10 right-0 text-white/80 hover:text-white text-2xl font-bold">&times;</button>
                            <img src="{{ $content->generated_image_url }}" alt="Preview Iklan"
                                 class="w-full max-h-[85vh] object-contain rounded-lg shadow-hero">
                        </div>
                    </div>
                @else
                    <div class="bg-surface border border-border rounded-lg shadow-card p-4">
                        <div class="max-h-64 h-48 bg-surface-alt rounded-lg flex items-center justify-center text-on-surface-variant text-xs font-semibold">
                            Belum ada gambar poster untuk draft ini.
                        </div>
                    </div>
                @endif

                {{-- Caption Text Area --}}
                <div class="bg-surface border border-border rounded-lg p-6 shadow-card space-y-3">
                    <h3 class="font-bold text-on-surface text-base font-display">Teks Caption & Hashtags</h3>
                    <p class="text-sm text-on-surface leading-relaxed whitespace-pre-line">{{ $content->caption }}</p>
                    <div class="flex flex-wrap gap-2 pt-2 border-t border-border/50">
                        @foreach($content->hashtags ?? [] as $tag)
                            <span class="text-xs font-semibold text-primary bg-primary-subtle px-2.5 py-1 rounded-full">{{ $tag }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Creative Brief Sidebar (1/3) --}}
            <div class="bg-surface border border-border rounded-lg p-6 shadow-card space-y-4 h-fit">
                <h3 class="font-bold text-on-surface text-base font-display">Creative Brief</h3>
                <div class="space-y-3 text-xs text-on-surface-variant">
                    <div>
                        <span class="block font-semibold">Nama Usaha:</span>
                        <span class="font-bold text-on-surface">{{ $profile->business_name }}</span>
                    </div>
                    <div>
                        <span class="block font-semibold">Prompt Asal:</span>
                        <span class="text-on-surface font-medium">{{ $content->prompt }}</span>
                    </div>
                    <div>
                        <span class="block font-semibold">Jenis Konten:</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-primary-subtle text-primary">{{ $content->content_type_label }}</span>
                    </div>
                </div>

                <div class="pt-4 border-t border-border space-y-2">
                    <span class="block text-xs font-semibold text-on-surface">Status Publikasi:</span>
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-success-bg text-success inline-block">Siap Diunggah</span>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
