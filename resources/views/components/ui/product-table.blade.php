@props([
    'products',
])

<div class="overflow-x-auto bg-surface border border-border rounded-lg shadow-card">
    <table class="w-full text-left text-xs text-on-surface">
        <thead class="bg-surface-alt text-on-surface-variant font-semibold uppercase tracking-wider border-b border-border">
            <tr>
                <th class="p-3.5">SKU / Produk</th>
                <th class="p-3.5">Kategori</th>
                <th class="p-3.5">Harga</th>
                <th class="p-3.5">Stok</th>
                <th class="p-3.5">Status</th>
                <th class="p-3.5 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border/40 font-medium">
            @forelse($products as $product)
                <tr class="hover:bg-surface-warm/50 transition">
                    <td class="p-3.5">
                        <div class="font-bold text-on-surface">{{ $product->name }}</div>
                        <div class="text-[10px] text-on-surface-variant/70">{{ $product->sku }}</div>
                    </td>
                    <td class="p-3.5 text-on-surface-variant">{{ $product->category }}</td>
                    <td class="p-3.5 font-bold">Rp {{ number_format($product->price, 0, ',', '.') }}</td>
                    <td class="p-3.5 font-bold">{{ $product->stock_level }} Unit</td>
                    <td class="p-3.5">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $product->status === 'in_stock' ? 'bg-success-bg text-success' : ($product->status === 'low_stock' ? 'bg-hero-accent text-primary' : 'bg-error/10 text-error') }}">
                            {{ str_replace('_', ' ', $product->status) }}
                        </span>
                    </td>
                    <td class="p-3.5 text-right">
                        <form action="{{ route('sistok.products.destroy', $product->id) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" onclick="return confirm('Hapus produk ini?')" class="text-error hover:underline text-xs font-semibold">
                                Hapus
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-8 text-center text-on-surface-variant">Belum ada produk di katalog SiStok.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
