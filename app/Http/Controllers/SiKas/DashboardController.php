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

        // Audit #7 fix: proper day-based grouping using actual timestamps
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        $todayTransactions = Transaction::where('umkm_id', $profile->id)
            ->with(['category', 'outlet'])
            ->whereDate('transaction_date', $today)
            ->latest('transaction_date')
            ->get();

        $yesterdayTransactions = Transaction::where('umkm_id', $profile->id)
            ->with(['category', 'outlet'])
            ->whereDate('transaction_date', $yesterday)
            ->latest('transaction_date')
            ->get();

        // Still keep a combined list for backward compat (latest 10)
        $transactions = Transaction::where('umkm_id', $profile->id)
            ->with(['category', 'outlet'])
            ->latest('transaction_date')
            ->take(10)
            ->get();

        $totalIncome  = Transaction::where('umkm_id', $profile->id)->where('type', 'income')->sum('amount');
        $totalExpense = Transaction::where('umkm_id', $profile->id)->where('type', 'expense')->sum('amount');
        $totalBalance = $totalIncome - $totalExpense;

        // Audit #2 fix: real chart data for last 7 days
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayIncome  = Transaction::where('umkm_id', $profile->id)
                ->where('type', 'income')
                ->whereDate('transaction_date', $date)
                ->sum('amount');
            $dayExpense = Transaction::where('umkm_id', $profile->id)
                ->where('type', 'expense')
                ->whereDate('transaction_date', $date)
                ->sum('amount');

            $chartData[] = [
                'date'    => now()->subDays($i)->locale('id')->isoFormat('ddd'),
                'income'  => (int) $dayIncome,
                'expense' => (int) $dayExpense,
            ];
        }

        $categories = Category::where(function ($q) use ($profile) {
            $q->where('is_system', true)
              ->orWhere('umkm_id', $profile->id);
        })->orderBy('name')->get();

        $outlets = Outlet::where('umkm_id', $profile->id)->get();

        $aiInsight = $this->insightService->generateInsight((int)$totalIncome, (int)$totalExpense);

        return view('sikas.dashboard', [
            'activeNav'              => 'sikas',
            'profile'                => $profile,
            'transactions'           => $transactions,
            'todayTransactions'      => $todayTransactions,
            'yesterdayTransactions'  => $yesterdayTransactions,
            'totalIncome'            => $totalIncome,
            'totalExpense'           => $totalExpense,
            'totalBalance'           => $totalBalance,
            'chartData'              => $chartData,
            'categories'             => $categories,
            'outlets'                => $outlets,
            'aiInsight'              => $aiInsight,
        ]);
    }
}
