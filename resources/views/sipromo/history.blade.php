<x-layouts.app title="Riwayat Konten — SiPromo" activeNav="sipromo">
    <div class="p-6 lg:p-8 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold font-display text-on-surface">Riwayat Konten SiPromo</h1>
                <p class="text-sm text-on-surface-variant font-medium">Semua hasil pembuatan gambar dan caption promosi AI.</p>
            </div>
            <a href="{{ route('sipromo.landing') }}" class="text-sm font-semibold text-primary hover:underline">← Buat Konten Baru</a>
        </div>

        @if($contents->isEmpty())
            <div class="p-8 bg-surface rounded-lg border border-border text-center text-on-surface-variant text-sm">
                Belum ada konten promosi yang tersimpan.
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($contents as $item)
                    <x-ui.content-preview-card
                        :title="$item->title"
                        :type="$item->content_type_label"
                        :imageUrl="$item->generated_image_url"
                        :caption="$item->caption"
                        :href="route('sipromo.preview', $item->id)"
                    />
                @endforeach
            </div>
            <div class="pt-4">
                {{ $contents->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>
