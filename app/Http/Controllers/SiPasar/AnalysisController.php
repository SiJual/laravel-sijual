<?php

namespace App\Http\Controllers\SiPasar;

use App\Http\Controllers\Controller;
use App\Models\Competitor;
use App\Models\MarketAnalysis;
use App\Models\UmkmProfile;
use App\Services\AI\MarketFitScoreService;
use App\Services\Market\BPSDataService;
use App\Services\Market\CompetitorScraperService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalysisController extends Controller
{
    public function __construct(
        private CompetitorScraperService $scraper,
        private BPSDataService $bpsService,
        private MarketFitScoreService $scoreService
    ) {}

    public function index(): View
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $latestAnalysis = MarketAnalysis::where('umkm_id', $profile->id)
            ->with('competitors')
            ->latest()
            ->first();

        return view('sipasar.landing', [
            'activeNav' => 'sipasar',
            'profile' => $profile,
            'latestAnalysis' => $latestAnalysis,
        ]);
    }

    public function analyze(Request $request): RedirectResponse
    {
        $request->validate([
            'location_query' => 'required|string|max:255',
            'radius_km' => 'required|numeric|min:0.5|max:10',
        ], [
            'location_query.required' => 'Lokasi riset pasar wajib diisi.',
            'radius_km.required' => 'Radius analisis wajib diisi.',
        ]);

        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $lat = $profile->latitude ?? -6.2444;
        $lng = $profile->longitude ?? 106.8006;

        $scrapedCompetitors = $this->scraper->discoverCompetitors($request->location_query, $lat, $lng, (float)$request->radius_km);
        $demographics = $this->bpsService->getDemographics($request->location_query);
        $score = $this->scoreService->calculateScore($profile->business_type ?? 'F&B', count($scrapedCompetitors), $demographics['density']);

        $analysis = \Illuminate\Support\Facades\DB::transaction(function () use ($profile, $request, $lat, $lng, $score, $demographics, $scrapedCompetitors) {
            $analysis = MarketAnalysis::create([
                'umkm_id' => $profile->id,
                'location_query' => $request->location_query,
                'latitude' => $lat,
                'longitude' => $lng,
                'radius_km' => $request->radius_km,
                'market_fit_score' => $score,
                'demographic_data' => $demographics,
                'status' => 'completed',
            ]);

            \App\Models\Demographic::create([
                'umkm_id' => $profile->id,
                'analysis_id' => $analysis->id,
                'area_name' => $request->location_query,
                'population_data' => $demographics['population'] ?? [],
                'income_data' => $demographics['income'] ?? [],
                'age_distribution' => $demographics['age_distribution'] ?? [],
                'data_source' => 'bps',
            ]);

            foreach ($scrapedCompetitors as $comp) {
                Competitor::create([
                    'analysis_id' => $analysis->id,
                    'name' => $comp['name'],
                    'business_type' => $comp['business_type'],
                    'rating' => $comp['rating'],
                    'review_count' => $comp['review_count'],
                    'sentiment' => $comp['sentiment'],
                    'address' => $comp['address'],
                    'latitude' => $comp['latitude'],
                    'longitude' => $comp['longitude'],
                ]);
            }

            return $analysis;
        });

        return redirect()->route('sipasar.results', $analysis->id)->with('success', 'Riset pasar berhasil dijalankan!');
    }

    public function results(string $id): View
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $analysis = MarketAnalysis::where('umkm_id', $profile->id)
            ->where('id', $id)
            ->with('competitors')
            ->firstOrFail();

        return view('sipasar.results', [
            'activeNav' => 'sipasar',
            'profile' => $profile,
            'analysis' => $analysis,
        ]);
    }
}
