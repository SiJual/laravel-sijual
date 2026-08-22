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

        $totalRevenue = Transaction::where('umkm_id', $profile->id)
            ->where('type', 'income')
            ->sum('amount');

        $recentTransactions = Transaction::where('umkm_id', $profile->id)
            ->with('category')
            ->latest('transaction_date')
            ->take(5)
            ->get();

        $activeCampaigns = ContentAsset::where('umkm_id', $profile->id)
            ->where('status', 'published')
            ->count();

        $latestAnalysis = MarketAnalysis::where('umkm_id', $profile->id)
            ->latest()
            ->first();

        $latestMarketScore = $latestAnalysis ? $latestAnalysis->market_fit_score : null;

        return view('hub.dashboard', [
            'activeNav' => 'hub',
            'profile' => $profile,
            'totalRevenue' => $totalRevenue,
            'recentTransactions' => $recentTransactions,
            'activeCampaigns' => $activeCampaigns,
            'marketScore' => $latestMarketScore,
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
