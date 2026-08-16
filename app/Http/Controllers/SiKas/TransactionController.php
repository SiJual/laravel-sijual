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

    public function store(TransactionRequest $request): RedirectResponse
    {
        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $outlet = null;
        if ($request->outlet_id) {
            $outlet = Outlet::where('umkm_id', $profile->id)->where('id', $request->outlet_id)->first();
            if (!$outlet) {
                return back()->withErrors(['outlet_id' => 'Outlet tidak valid atau bukan milik Anda.'])->withInput();
            }
        } else {
            $outlet = Outlet::where('umkm_id', $profile->id)->where('is_primary', true)->first();
        }

        $category = Category::where('id', $request->category_id)
            ->where(function ($q) use ($profile) {
                $q->where('is_system', true)->orWhere('umkm_id', $profile->id);
            })->first();

        if (!$category) {
            return back()->withErrors(['category_id' => 'Kategori tidak valid.'])->withInput();
        }

        Transaction::create([
            'umkm_id' => $profile->id,
            'outlet_id' => $outlet ? $outlet->id : null,
            'category_id' => $category->id,
            'type' => $request->type,
            'amount' => $request->amount,
            'description' => $request->description,
            'notes' => $request->notes,
            'source' => $request->source ?? 'manual',
            'payment_method' => $request->payment_method ?? 'cash',
            'transaction_date' => $request->transaction_date,
        ]);

        return back()->with('success', 'Transaksi berhasil dicatat!');
    }

    public function show(string $id): \Illuminate\Http\JsonResponse
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $transaction = Transaction::where('umkm_id', $profile->id)
            ->with(['category', 'outlet'])
            ->where('id', $id)
            ->firstOrFail();

        return response()->json($transaction);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $transaction = Transaction::where('umkm_id', $profile->id)->where('id', $id)->firstOrFail();

        $request->validate([
            'type'             => 'required|in:income,expense',
            'amount'           => 'required|numeric|min:1',
            'description'      => 'nullable|string|max:500',
            'category_id'      => 'required|string',
            'transaction_date' => 'required|date',
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

        $transaction->update([
            'type'             => $request->type,
            'amount'           => $request->amount,
            'description'      => $request->description,
            'category_id'      => $category->id,
            'transaction_date' => $request->transaction_date,
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
