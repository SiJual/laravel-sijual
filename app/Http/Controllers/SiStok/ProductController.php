<?php

namespace App\Http\Controllers\SiStok;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\UmkmProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $query = Product::where('umkm_id', $profile->id);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }

        $products = $query->latest()->paginate(10);

        // 1 consolidated query for all dashboard stats
        $metrics = Product::where('umkm_id', $profile->id)
            ->selectRaw("
                COUNT(*) as total_products,
                COUNT(CASE WHEN stock_level <= low_stock_threshold THEN 1 END) as low_stock_items,
                COALESCE(SUM(price * stock_level), 0) as est_value
            ")
            ->first();

        $totalProducts = (int) ($metrics->total_products ?? 0);
        $lowStockItems = (int) ($metrics->low_stock_items ?? 0);
        $estValue = (int) ($metrics->est_value ?? 0);

        return view('sistok.index', [
            'activeNav' => 'sistok',
            'profile' => $profile,
            'products' => $products,
            'totalProducts' => $totalProducts,
            'lowStockItems' => $lowStockItems,
            'estValue' => $estValue,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'category' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'stock_level' => 'required|integer|min:0',
            'image_url' => 'nullable|url|max:2048',
        ]);

        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $threshold = 5;
        $status = $request->stock_level == 0 ? 'out_of_stock' : ($request->stock_level <= $threshold ? 'low_stock' : 'in_stock');

        Product::create([
            'umkm_id' => $profile->id,
            'name' => $request->name,
            'sku' => $request->sku ?? $this->generateSku($request->name, $profile->id),
            'category' => $request->category,
            'price' => $request->price,
            'stock_level' => $request->stock_level,
            'status' => $status,
            'image_url' => $request->image_url,
            'low_stock_threshold' => $threshold,
        ]);

        return back()->with('success', 'Produk baru berhasil ditambahkan ke SiStok!');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'category'    => 'required|string|max:100',
            'price'       => 'required|numeric|min:0',
            'stock_level' => 'required|integer|min:0',
            'image_url'   => 'nullable|url|max:2048',
        ]);

        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $product = Product::where('umkm_id', $profile->id)->where('id', $id)->firstOrFail();

        $threshold = $product->low_stock_threshold ?? 5;
        $status = $request->stock_level == 0
            ? 'out_of_stock'
            : ($request->stock_level <= $threshold ? 'low_stock' : 'in_stock');

        $product->update([
            'name'        => $request->name,
            'category'    => $request->category,
            'price'       => $request->price,
            'stock_level' => $request->stock_level,
            'status'      => $status,
            'image_url'   => $request->image_url,
        ]);

        return back()->with('success', 'Produk berhasil diperbarui!');
    }

    public function destroy(string $id): RedirectResponse
    {
        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $product = Product::where('umkm_id', $profile->id)->where('id', $id)->firstOrFail();
        $product->delete();

        return back()->with('success', 'Produk berhasil dihapus dari SiStok.');
    }

    private function generateSku(string $productName, string $umkmId): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', $productName));
        $prefix = substr($prefix, 0, 3) ?: 'PRD';

        $maxId = (int) Product::max('id') + 1;
        $sequence = str_pad($maxId, 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$sequence}";
    }
}
