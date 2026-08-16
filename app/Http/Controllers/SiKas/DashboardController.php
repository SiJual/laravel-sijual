<?php

namespace App\Http\Controllers\SiKas;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Transaction;
use App\Models\UmkmProfile;
use App\Services\AI\FinancialInsightService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private FinancialInsightService $insightService) {}

    public function index(): View
    {
        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $transactions = Transaction::where('umkm_id', $profile->id)
            ->with(['category', 'outlet'])
            ->latest('transaction_date')
            ->take(10)
            ->get();

        $totalIncome = Transaction::where('umkm_id', $profile->id)->where('type', 'income')->sum('amount');
        $totalExpense = Transaction::where('umkm_id', $profile->id)->where('type', 'expense')->sum('amount');
        $totalBalance = $totalIncome - $totalExpense;

        $categories = Category::where(function ($q) use ($profile) {
            $q->where('is_system', true)
              ->orWhere('umkm_id', $profile->id);
        })->orderBy('name')->get();

        $outlets = Outlet::where('umkm_id', $profile->id)->get();

        $aiInsight = $this->insightService->generateInsight((int)$totalIncome, (int)$totalExpense);

        return view('sikas.dashboard', [
            'activeNav' => 'sikas',
            'profile' => $profile,
            'transactions' => $transactions,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'totalBalance' => $totalBalance,
            'categories' => $categories,
            'outlets' => $outlets,
            'aiInsight' => $aiInsight,
        ]);
    }
}
