<x-layouts.app title="SiStok — Manajemen Inventori" activeNav="sistok">
    <div x-data="{ showModal: false }" class="p-6 lg:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold font-display text-on-surface">SiStok — Katalog Produk & Stok</h1>
                <p class="text-sm text-on-surface-variant font-medium">Monitoring level stok, estimasi nilai inventori, dan pemantauan stok menipis.</p>
            </div>
            <button @click="showModal = true" type="button" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-md shadow-card hover:bg-primary/90 transition">
                + Tambah Produk Baru
            </button>
        </div>

        @if(session('success'))
            <div class="p-4 rounded-md bg-success-bg text-success text-sm font-semibold">
                {{ session('success') }}
            </div>
        @endif

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-ui.stat-card
                title="Total Produk"
                value="{{ $totalProducts }} Item"
                icon="📦"
            />
            <x-ui.stat-card
                title="Stok Menipis (Alert)"
                value="{{ $lowStockItems }} Produk"
                icon="⚠️"
                trend="{{ $lowStockItems > 0 ? 'Perlu Restok' : 'Aman' }}"
            />
            <x-ui.stat-card
                title="Est. Nilai Inventori"
                value="Rp {{ number_format($estValue, 0, ',', '.') }}"
                icon="💎"
            />
        </div>

        {{-- Filter & Product Table --}}
        <div class="space-y-4">
            <form method="GET" action="{{ route('sistok.products.index') }}" class="flex flex-wrap items-center gap-3 p-4 bg-surface rounded-lg border border-border">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama produk..."
                       class="px-3.5 py-2 bg-surface-alt border border-border-input rounded-full text-xs text-on-surface focus:ring-2 focus:ring-primary/20 outline-none w-48 sm:w-64">

                <select name="category" onchange="this.form.submit()" class="px-3 py-2 bg-surface-alt border border-border-input rounded-full text-xs text-on-surface focus:ring-2 focus:ring-primary/20 outline-none">
                    <option value="">Semua Kategori</option>
                    <option value="Kuliner / F&B" {{ request('category') === 'Kuliner / F&B' ? 'selected' : '' }}>Kuliner / F&B</option>
                    <option value="Fashion & Batik" {{ request('category') === 'Fashion & Batik' ? 'selected' : '' }}>Fashion & Batik</option>
                    <option value="Kerajinan Tangan" {{ request('category') === 'Kerajinan Tangan' ? 'selected' : '' }}>Kerajinan Tangan</option>
                    <option value="Umum" {{ request('category') === 'Umum' ? 'selected' : '' }}>Umum</option>
                </select>

                <button type="submit" class="px-4 py-2 bg-primary text-white font-semibold text-xs rounded-full">Filter</button>
            </form>

            <x-ui.product-table :products="$products" />

            <div class="pt-2">
                {{ $products->links() }}
            </div>
        </div>

        {{-- Add Product Modal --}}
        <div x-show="showModal"
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/50 backdrop-blur-sm"
             style="display: none;">
            <div @click.away="showModal = false" class="bg-surface rounded-xl border border-border p-6 w-full max-w-md shadow-hero space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <h3 class="text-lg font-bold font-display text-on-surface">Tambah Produk Baru</h3>
                    <button @click="showModal = false" type="button" class="text-on-surface-variant hover:text-on-surface text-lg font-bold">&times;</button>
                </div>

                <form action="{{ route('sistok.products.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <x-form.input label="Nama Produk" name="name" placeholder="Contoh: Kopi Bubuk Arabika 250g" required />
                    <x-form.input label="SKU / Kode Produk (Opsional)" name="sku" placeholder="SKU-8821" />

                    <x-form.select label="Kategori Produk" name="category" required>
                        <option value="Kuliner / F&B">Kuliner / F&B</option>
                        <option value="Fashion & Batik">Fashion & Batik</option>
                        <option value="Kerajinan Tangan">Kerajinan Tangan</option>
                        <option value="Umum">Umum</option>
                    </x-form.select>

                    <div class="grid grid-cols-2 gap-3">
                        <x-form.input label="Harga Satuan (Rp)" name="price" type="number" placeholder="35000" required />
                        <x-form.input label="Jumlah Stok" name="stock_level" type="number" placeholder="20" required />
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-border">
                        <x-form.button type="button" variant="secondary" @click="showModal = false">Batal</x-form.button>
                        <x-form.button type="submit" variant="primary">Simpan Produk</x-form.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
