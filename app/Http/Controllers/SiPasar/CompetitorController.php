<?php

namespace App\Http\Controllers\SiPasar;

use App\Http\Controllers\Controller;
use App\Models\Competitor;
use App\Models\MarketAnalysis;
use App\Models\UmkmProfile;
use Illuminate\Http\JsonResponse;

class CompetitorController extends Controller
{
    public function index(string $analysisId): JsonResponse
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $analysis = MarketAnalysis::where('umkm_id', $profile->id)->where('id', $analysisId)->firstOrFail();
        $competitors = Competitor::where('analysis_id', $analysis->id)->get();

        return response()->json([
            'status' => 'success',
            'data' => $competitors,
        ]);
    }
}
