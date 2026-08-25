<?php

namespace App\Http\Controllers\SiKas;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\UmkmProfile;
use App\Services\AI\FinancialInsightService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private FinancialInsightService $insightService) {}

    public function index(Request $request): View
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        // 1. Target Cuan & Period Configuration
        $targetNominal = (int) ($profile->target_cuan ?: 100000);
        $targetPeriod = $profile->target_cuan_period ?: 'monthly';

        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        $startOfQuarter = $now->copy()->firstOfQuarter();
        $endOfQuarter = $now->copy()->lastOfQuarter();

        if ($now->month <= 6) {
            $startOfSemester = $now->copy()->startOfYear();
            $endOfSemester = $now->copy()->startOfYear()->addMonths(5)->endOfMonth();
        } else {
            $startOfSemester = $now->copy()->startOfYear()->addMonths(6)->startOfMonth();
            $endOfSemester = $now->copy()->endOfYear();
        }

        $startOfYear = $now->copy()->startOfYear();
        $endOfYear = $now->copy()->endOfYear();

        switch ($targetPeriod) {
            case 'quarterly':
                $periodLabel = 'Kuartal ' . ceil($now->month / 3) . ' ' . $now->year;
                $periodName = '3 Bulan';
                break;
            case 'semester':
                $periodLabel = 'Semester ' . ($now->month <= 6 ? '1' : '2') . ' ' . $now->year;
                $periodName = '6 Bulan';
                break;
            case 'yearly':
                $periodLabel = 'Tahun ' . $now->year;
                $periodName = '1 Tahun';
                break;
            case 'monthly':
            default:
                $periodLabel = $now->locale('id')->isoFormat('MMMM Y');
                $periodName = '1 Bulan';
                break;
        }

        // 2. ULTRA LIGHTWEIGHT: 1 Single Aggregated SQL Query for all metrics
        $stats = Transaction::where('umkm_id', $profile->id)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) as total_balance,
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as all_time_income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as all_time_expense,
                COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date >= ? AND transaction_date <= ? THEN amount ELSE 0 END), 0) as this_month_income,
                COALESCE(SUM(CASE WHEN type = 'expense' AND transaction_date >= ? AND transaction_date <= ? THEN amount ELSE 0 END), 0) as this_month_expense,
                COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date >= ? AND transaction_date <= ? THEN amount ELSE 0 END), 0) as last_month_income,
                COALESCE(SUM(CASE WHEN type = 'expense' AND transaction_date >= ? AND transaction_date <= ? THEN amount ELSE 0 END), 0) as last_month_expense,
                COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date >= ? AND transaction_date <= ? THEN amount ELSE 0 END), 0) as quarterly_income,
                COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date >= ? AND transaction_date <= ? THEN amount ELSE 0 END), 0) as semester_income,
                COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date >= ? AND transaction_date <= ? THEN amount ELSE 0 END), 0) as yearly_income
            ", [
                $startOfMonth, $endOfMonth,
                $startOfMonth, $endOfMonth,
                $startOfLastMonth, $endOfLastMonth,
                $startOfLastMonth, $endOfLastMonth,
                $startOfQuarter, $endOfQuarter,
                $startOfSemester, $endOfSemester,
                $startOfYear, $endOfYear
            ])
            ->first();

        $totalBalance      = (float) ($stats->total_balance ?? 0);
        $allTimeIncome     = (float) ($stats->all_time_income ?? 0);
        $allTimeExpense    = (float) ($stats->all_time_expense ?? 0);
        $thisMonthIncome   = (float) ($stats->this_month_income ?? 0);
        $thisMonthExpense  = (float) ($stats->this_month_expense ?? 0);
        $lastMonthIncome   = (float) ($stats->last_month_income ?? 0);
        $lastMonthExpense  = (float) ($stats->last_month_expense ?? 0);
        $quarterlyIncome   = (float) ($stats->quarterly_income ?? 0);
        $semesterIncome    = (float) ($stats->semester_income ?? 0);
        $yearlyIncome      = (float) ($stats->yearly_income ?? 0);

        $periodIncomes = [
            'monthly'   => (int) $thisMonthIncome,
            'quarterly' => (int) $quarterlyIncome,
            'semester'  => (int) $semesterIncome,
            'yearly'    => (int) $yearlyIncome,
        ];

        $periodIncome = $periodIncomes[$targetPeriod] ?? (int) $thisMonthIncome;

        // Month-on-Month Growth calculation
        if ($lastMonthIncome > 0) {
            $growthPercent = round((($thisMonthIncome - $lastMonthIncome) / $lastMonthIncome) * 100, 1);
        } else {
            $growthPercent = $thisMonthIncome > 0 ? 100.0 : 0.0;
        }

        // Expense Ratio for CURRENT MONTH: pengeluaran dibanding pemasukan bulan ini
        if ($thisMonthIncome > 0) {
            $expenseRatio = min(100, round(($thisMonthExpense / $thisMonthIncome) * 100, 1));
        } elseif ($thisMonthExpense > 0) {
            $expenseRatio = 100.0; // 100% beban (belum ada pemasukan)
        } else {
            $expenseRatio = 0.0;
        }

        // Target Cuan progress against target
        $targetProgress = $targetNominal > 0 ? min(100, round(($periodIncome / $targetNominal) * 100, 1)) : 0;

        // 3. Category Breakdown for This Month's Expenses (for Quick Modal)
        $thisMonthExpenseCategories = Transaction::where('umkm_id', $profile->id)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->with('category')
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->take(6)
            ->get();

        // 4. Latest 50 transactions for Today and Yesterday lists
        $recentTransactions = Transaction::where('umkm_id', $profile->id)
            ->with(['category', 'outlet'])
            ->latest('transaction_date')
            ->take(50)
            ->get();

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        $todayTransactions = $recentTransactions->filter(fn($t) => $t->transaction_date?->format('Y-m-d') === $today)->values();
        $yesterdayTransactions = $recentTransactions->filter(fn($t) => $t->transaction_date?->format('Y-m-d') === $yesterday)->values();
        $transactions = $recentTransactions->take(10);

        // 5. 7-Day Chart Data
        $sevenDaysAgo = now()->subDays(6)->startOfDay();
        $dailyTotals = Transaction::where('umkm_id', $profile->id)
            ->where('transaction_date', '>=', $sevenDaysAgo)
            ->selectRaw("DATE(transaction_date) as t_date, type, SUM(amount) as total")
            ->groupByRaw("DATE(transaction_date), type")
            ->get()
            ->groupBy('t_date');

        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $dateObj = now()->subDays($i);
            $dateStr = $dateObj->format('Y-m-d');
            $dayRows = $dailyTotals->get($dateStr, collect());

            $dayIncome  = $dayRows->where('type', 'income')->sum('total');
            $dayExpense = $dayRows->where('type', 'expense')->sum('total');

            $chartData[] = [
                'date'    => $dateObj->locale('id')->isoFormat('ddd'),
                'income'  => (int) $dayIncome,
                'expense' => (int) $dayExpense,
            ];
        }

        $categories = Category::where(function ($q) use ($profile) {
            $q->where('is_system', true)
              ->orWhere('umkm_id', $profile->id);
        })->orderBy('name')->get();

        $outlets = Outlet::where('umkm_id', $profile->id)->get();

        // SiStok catalogue for the quick-entry form.
        $products = Product::where('umkm_id', $profile->id)->orderBy('name')->get();

        // 6. AI Insight
        $aiInsight = $this->insightService->generateInsight((int) $allTimeIncome, (int) $allTimeExpense);

        return view('sikas.dashboard', [
            'activeNav' => 'sikas',
            'profile' => $profile,
            'totalBalance' => $totalBalance,
            'allTimeIncome' => $allTimeIncome,
            'allTimeExpense' => $allTimeExpense,
            'totalIncome' => $allTimeIncome,
            'totalExpense' => $thisMonthExpense, // Bulan berjalan
            'thisMonthIncome' => $thisMonthIncome,
            'thisMonthExpense' => $thisMonthExpense,
            'lastMonthIncome' => $lastMonthIncome,
            'lastMonthExpense' => $lastMonthExpense,
            'growthPercent' => $growthPercent,
            'expenseRatio' => $expenseRatio,
            'targetNominal' => $targetNominal,
            'targetPeriod' => $targetPeriod,
            'periodLabel' => $periodLabel,
            'periodName' => $periodName,
            'periodIncome' => $periodIncome,
            'periodIncomes' => $periodIncomes,
            'targetProgress' => $targetProgress,
            'thisMonthExpenseCategories' => $thisMonthExpenseCategories,
            'chartData' => $chartData,
            'todayTransactions' => $todayTransactions,
            'yesterdayTransactions' => $yesterdayTransactions,
            'transactions' => $transactions,
            'categories' => $categories,
            'outlets' => $outlets,
            'products' => $products,
            'aiInsight' => $aiInsight,
        ]);
    }

    public function updateTargetCuan(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'target_cuan' => 'required|numeric|min:1000',
            'target_cuan_period' => 'required|in:monthly,quarterly,semester,yearly',
        ]);

        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $profile->update([
            'target_cuan' => (int) $request->target_cuan,
            'target_cuan_period' => $request->target_cuan_period,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Target cuan berhasil diperbarui!',
                'target_cuan' => $profile->target_cuan,
                'target_cuan_period' => $profile->target_cuan_period,
            ]);
        }

        return back()->with('success', 'Target cuan dan periode berhasil diperbarui!');
    }
}
