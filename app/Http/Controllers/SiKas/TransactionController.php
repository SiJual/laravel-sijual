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
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

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
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $outlet = $request->outlet_id
            ? Outlet::where('umkm_id', $profile->id)->where('id', $request->outlet_id)->first()
            : Outlet::where('umkm_id', $profile->id)->where('is_primary', true)->first();

        Transaction::create([
            'umkm_id' => $profile->id,
            'outlet_id' => $outlet ? $outlet->id : null,
            'category_id' => $request->category_id,
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

    public function destroy(string $id): RedirectResponse
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $transaction = Transaction::where('umkm_id', $profile->id)->where('id', $id)->firstOrFail();
        $transaction->delete();

        return back()->with('success', 'Transaksi berhasil dihapus.');
    }
}
