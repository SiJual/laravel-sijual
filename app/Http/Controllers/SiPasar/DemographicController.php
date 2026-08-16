<?php

namespace App\Http\Controllers\SiPasar;

use App\Http\Controllers\Controller;
use App\Models\MarketAnalysis;
use App\Models\UmkmProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DemographicController extends Controller
{
    public function index(string $analysisId): JsonResponse
    {
        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        $analysis = MarketAnalysis::where('umkm_id', $profile->id)->where('id', $analysisId)->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $analysis->demographic_data,
        ]);
    }
}
