<x-layouts.app title="Pratinjau Konten — SiPromo" activeNav="sipromo">
    <div class="p-6 lg:p-8 space-y-6">
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
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Image Preview (2/3) --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-card p-4">
                    <div class="aspect-square bg-surface-alt rounded-lg overflow-hidden relative">
                        <img src="{{ $content->generated_image_url }}" alt="Preview Iklan" class="w-full h-full object-cover">
                    </div>
                </div>

                {{-- Caption Text Area --}}
                <div class="bg-surface border border-border rounded-lg p-6 shadow-card space-y-3">
                    <h3 class="font-bold text-on-surface text-base font-display">Teks Caption & Hashtags</h3>
                    <p class="text-sm text-on-surface leading-relaxed whitespace-pre-line">{{ $content->caption }}</p>
                    <div class="flex flex-wrap gap-2 pt-2 border-t border-border/50">
                        @foreach($content->hashtags as $tag)
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
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-primary-subtle text-primary">{{ $content->content_type }}</span>
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
