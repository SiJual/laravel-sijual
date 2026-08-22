<?php

namespace App\Http\Controllers\SiKas;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Transaction;
use App\Models\UmkmProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $query = Transaction::where('umkm_id', $profile->id)->with(['category', 'outlet']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('description', 'ilike', '%' . $request->search . '%');
        }

        $transactions = $query->latest('transaction_date')->paginate(15);

        $categories = Category::where(function ($q) use ($profile) {
            $q->where('is_system', true)->orWhere('umkm_id', $profile->id);
        })->get();

        $outlets = Outlet::where('umkm_id', $profile->id)->get();

        return view('sikas.transactions', [
            'activeNav' => 'sikas',
            'profile' => $profile,
            'transactions' => $transactions,
            'categories' => $categories,
            'outlets' => $outlets,
        ]);
    }

    public function store(TransactionRequest $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $outlet = null;
        if ($request->outlet_id) {
            $outlet = Outlet::where('umkm_id', $profile->id)->where('id', $request->outlet_id)->first();
        }
        if (!$outlet) {
            $outlet = Outlet::where('umkm_id', $profile->id)->where('is_primary', true)->first();
        }

        $category = null;
        if ($request->filled('category_id')) {
            $category = Category::where('id', $request->category_id)
                ->where(function ($q) use ($profile) {
                    $q->where('is_system', true)->orWhere('umkm_id', $profile->id);
                })->first();
        }

        // Auto-match by category_name (e.g. from Voice AI or custom input)
        if (!$category && $request->filled('category_name')) {
            $category = Category::where('name', 'ilike', '%' . trim($request->category_name) . '%')
                ->where(function ($q) use ($profile) {
                    $q->where('is_system', true)->orWhere('umkm_id', $profile->id);
                })->first();
        }

        // Fallback to default system category according to type
        if (!$category) {
            $category = Category::where('is_system', true)
                ->where('type', $request->type)
                ->orderBy('sort_order')
                ->first();

            if (!$category) {
                $category = Category::first();
            }
        }

        $rawDate = $request->transaction_date;
        if (empty($rawDate)) {
            $txDate = now();
        } else {
            $cleaned = str_replace('T', ' ', trim($rawDate));
            if (strlen($cleaned) === 10) {
                $txDate = \Carbon\Carbon::parse($cleaned . ' ' . now()->format('H:i:s'));
            } else {
                $txDate = \Carbon\Carbon::parse($cleaned);
            }
        }

        try {
            $tx = Transaction::create([
                'umkm_id' => $profile->id,
                'outlet_id' => $outlet ? $outlet->id : null,
                'category_id' => $category ? $category->id : null,
                'type' => $request->type,
                'amount' => $request->amount,
                'description' => $request->description,
                'notes' => $request->notes,
                'source' => $request->source ?? 'manual',
                'payment_method' => $request->payment_method ?? 'cash',
                'transaction_date' => $txDate,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Transaksi berhasil dicatat!',
                    'data' => $tx->load(['category', 'outlet']),
                ]);
            }

            return back()->with('success', 'Transaksi berhasil dicatat!');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error saving transaction: ' . $e->getMessage());
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mencatat transaksi: ' . $e->getMessage(),
                ], 500);
            }
            return back()->withErrors(['error' => 'Gagal mencatat transaksi: ' . $e->getMessage()]);
        }
    }

    public function show(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $transaction = Transaction::where('umkm_id', $profile->id)
            ->with(['category', 'outlet'])
            ->where('id', $id)
            ->firstOrFail();

        return response()->json($transaction);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $transaction = Transaction::where('umkm_id', $profile->id)->where('id', $id)->firstOrFail();

        $request->validate([
            'type'             => 'required|in:income,expense',
            'amount'           => 'required|numeric|min:1',
            'description'      => 'nullable|string|max:500',
            'category_id'      => 'required|string',
            'transaction_date' => 'required',
            'payment_method'   => 'nullable|string|max:50',
            'notes'            => 'nullable|string|max:1000',
        ]);

        // Verify category ownership
        $category = Category::where('id', $request->category_id)
            ->where(function ($q) use ($profile) {
                $q->where('is_system', true)->orWhere('umkm_id', $profile->id);
            })->first();

        if (!$category) {
            return back()->withErrors(['category_id' => 'Kategori tidak valid.'])->withInput();
        }

        $rawDate = $request->transaction_date;
        if (empty($rawDate)) {
            $txDate = $transaction->transaction_date ?? now();
        } else {
            $cleaned = str_replace('T', ' ', trim($rawDate));
            if (strlen($cleaned) === 10) {
                $timePart = $transaction->transaction_date ? $transaction->transaction_date->format('H:i:s') : now()->format('H:i:s');
                $txDate = \Carbon\Carbon::parse($cleaned . ' ' . $timePart);
            } else {
                $txDate = \Carbon\Carbon::parse($cleaned);
            }
        }

        $transaction->update([
            'type'             => $request->type,
            'amount'           => $request->amount,
            'description'      => $request->description,
            'category_id'      => $category->id,
            'transaction_date' => $txDate,
            'payment_method'   => $request->payment_method ?? $transaction->payment_method,
            'notes'            => $request->notes,
        ]);

        return back()->with('success', 'Transaksi berhasil diperbarui!');
    }

    public function destroy(string $id): RedirectResponse
    {
        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $transaction = Transaction::where('umkm_id', $profile->id)->where('id', $id)->firstOrFail();
        $transaction->delete();

        return back()->with('success', 'Transaksi berhasil dihapus.');
    }
}
