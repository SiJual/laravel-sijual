<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContentAsset;
use App\Models\MarketAnalysis;
use App\Models\Transaction;
use App\Models\UmkmProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class HubController extends Controller
{
    public function index(): View
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->first();

        $recentTransactions = collect();
        $totalRevenue = 0;
        $activeCampaigns = 0;
        $latestMarketScore = 0;

        if ($profile) {
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

            $latestMarketScore = $latestAnalysis ? $latestAnalysis->market_fit_score : 85;
        }

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
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->first();

        if (!$profile) {
            return response()->json(['error' => 'Profil tidak ditemukan'], 404);
        }

        $income = Transaction::where('umkm_id', $profile->id)->where('type', 'income')->sum('amount');
        $expense = Transaction::where('umkm_id', $profile->id)->where('type', 'expense')->sum('amount');

        return response()->json([
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
        ]);
    }
}
