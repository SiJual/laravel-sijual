<?php

namespace App\Http\Controllers\SiPasar;

use App\Http\Controllers\Controller;
use App\Models\Competitor;
use App\Models\MarketAnalysis;
use App\Models\UmkmProfile;
use App\Helpers\GeoHelper;
use App\Services\AI\MarketFitScoreService;
use App\Services\Market\BPSDataService;
use App\Services\Market\CompetitorScraperService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

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

        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        // 1. Get Demographics & Geocode location via Mapbox & Overpass
        $inputLat = $request->filled('latitude') ? (float) $request->input('latitude') : ($profile->latitude ?? -6.2444);
        $inputLng = $request->filled('longitude') ? (float) $request->input('longitude') : ($profile->longitude ?? 106.8006);

        $demographics = $this->bpsService->getDemographics(
            $request->location_query, 
            (float) $request->radius_km, 
            $inputLat, 
            $inputLng
        );

        $lat = $demographics['lat'] ?? $inputLat;
        $lng = $demographics['lng'] ?? $inputLng;

        // 2. Synthesize Competitors via OpenAI Structured Outputs
        $scrapedCompetitors = $this->scraper->discoverCompetitors(
            $request->location_query, 
            $lat, 
            $lng, 
            (float) $request->radius_km, 
            $demographics, 
            $profile->business_type ?? 'F&B'
        );

        // 3. Calculate Market Fit Score
        $score = $this->scoreService->calculateScore(
            $profile->business_type ?? 'F&B', 
            count($scrapedCompetitors), 
            $demographics['density'] ?? 'Sedang'
        );

        $analysis = \Illuminate\Support\Facades\DB::transaction(function () use ($profile, $request, $lat, $lng, $score, $demographics, $scrapedCompetitors) {
            $analysis = MarketAnalysis::create([
                'umkm_id' => $profile->id,
                'location_query' => $demographics['area'] ?? $request->location_query,
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
                'area_name' => $demographics['area'] ?? $request->location_query,
                'population_data' => ['total' => $demographics['population'] ?? 48500],
                'income_data' => ['avg_monthly' => $demographics['avg_monthly_income'] ?? 6500000],
                'age_distribution' => $demographics['age_distribution'] ?? [],
                'data_source' => 'mapbox_overpass',
            ]);

            foreach ($scrapedCompetitors as $comp) {
                // Calculate precise coordinates using GeoHelper
                $dist = $comp['distance_in_meters'] ?? 500;
                $bearing = $comp['bearing_degrees'] ?? rand(0, 359);
                $coords = GeoHelper::calculateOffsetCoordinates($lat, $lng, $dist, $bearing);

                $address = "Sekitar " . ($demographics['area'] ?? $request->location_query) . " (" . $dist . "m)";

                Competitor::create([
                    'analysis_id' => $analysis->id,
                    'name' => $comp['name'],
                    'business_type' => $comp['business_type'] ?? 'F&B',
                    'rating' => $comp['rating'] ?? 4.0,
                    'review_count' => $comp['review_count'] ?? 10,
                    'sentiment' => $comp['sentiment'] ?? 'neutral',
                    'address' => $address,
                    'latitude' => $coords['lat'],
                    'longitude' => $coords['lng'],
                ]);
            }

            return $analysis;
        });

        return redirect()->route('sipasar.results', $analysis->id)->with('success', 'Riset pasar berhasil dijalankan!');
    }

    public function results(string $id): View
    {
        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

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

    /**
     * Audit #16 fix: show all past analysis history.
     */
    public function history(): View
    {
        $userSession = session('supabase_user');
        $profile = UmkmProfile::where('user_id', $userSession['id'])->firstOrFail();

        $analyses = MarketAnalysis::where('umkm_id', $profile->id)
            ->withCount('competitors')
            ->latest()
            ->paginate(10);

        return view('sipasar.history', [
            'activeNav' => 'sipasar',
            'profile' => $profile,
            'analyses' => $analyses,
        ]);
    }
}
