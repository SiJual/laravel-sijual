<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContentAsset;
use App\Models\MarketAnalysis;
use App\Models\Transaction;
use App\Models\UmkmProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HubController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->first();

        $todayRevenue = Transaction::where('umkm_id', $profile->id)
            ->where('type', 'income')
            ->whereDate('transaction_date', today())
            ->sum('amount');

        $yesterdayRevenue = Transaction::where('umkm_id', $profile->id)
            ->where('type', 'income')
            ->whereDate('transaction_date', today()->subDay())
            ->sum('amount');

        // Only meaningful when there is something to compare against.
        $revenueChange = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100)
            : null;

        $recentTransactions = Transaction::where('umkm_id', $profile->id)
            ->with('category')
            ->latest('transaction_date')
            ->take(5)
            ->get();

        $publishedCampaigns = ContentAsset::where('umkm_id', $profile->id)
            ->where('status', 'published')
            ->latest()
            ->get();

        $latestAnalysis = MarketAnalysis::where('umkm_id', $profile->id)
            ->latest()
            ->first();

        $latestMarketScore = $latestAnalysis ? $latestAnalysis->market_fit_score : null;

        return view('hub.dashboard', [
            'activeNav' => 'hub',
            'profile' => $profile,
            'todayRevenue' => $todayRevenue,
            'revenueChange' => $revenueChange,
            'recentTransactions' => $recentTransactions,
            'activeCampaigns' => $publishedCampaigns->count(),
            'latestCampaign' => $publishedCampaigns->first(),
            'marketScore' => $latestMarketScore,
            'latestAnalysis' => $latestAnalysis,
        ]);
    }

    public function stats(): JsonResponse
    {
        $profile = UmkmProfile::where('user_id', Auth::id())->first();

        $income = Transaction::where('umkm_id', $profile->id)->where('type', 'income')->sum('amount');
        $expense = Transaction::where('umkm_id', $profile->id)->where('type', 'expense')->sum('amount');

        return response()->json([
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
        ]);
    }
}
