<?php

namespace App\Http\Controllers\SiPasar;

use App\Http\Controllers\Controller;
use App\Models\Competitor;
use App\Models\Demographic;
use App\Models\MarketAnalysis;
use App\Models\UmkmProfile;
use App\Services\Market\SiPasarBridgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AnalysisController extends Controller
{
    public function __construct(private SiPasarBridgeService $bridge) {}

    public function index(Request $request): View
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

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
        // The Python sidecar's Overpass/BPS lookup can legitimately take longer than
        // PHP's default 30s max_execution_time on wide radii / dense areas.
        set_time_limit(60);

        $request->validate([
            'location_query' => 'required|string|max:255',
            'radius_km' => 'required|numeric|min:0.5|max:10',
            'category' => 'required|string|max:100',
        ], [
            'location_query.required' => 'Lokasi riset pasar wajib diisi.',
            'radius_km.required' => 'Radius analisis wajib diisi.',
            'category.required' => 'Kategori usaha wajib dipilih.',
        ]);

        $profile = UmkmProfile::where('user_id', Auth::id())->firstOrFail();

        if (! $this->bridge->isHealthy()) {
            return redirect()->route('sipasar.landing')
                ->with('error', 'Layanan AI Riset Pasar (Python sidecar) sedang tidak aktif. Jalankan "composer run dev" atau start service-nya secara manual.');
        }

        $lat = $request->filled('latitude') ? (float) $request->input('latitude') : ($profile->latitude ?? -6.2444);
        $lng = $request->filled('longitude') ? (float) $request->input('longitude') : ($profile->longitude ?? 106.8006);
        $radiusMeters = (int) round($request->radius_km * 1000);

        try {
            $result = $this->bridge->analyze($profile->id, $lat, $lng, $request->category, $radiusMeters);
        } catch (\Throwable $e) {
            return redirect()->route('sipasar.landing')
                ->with('error', 'Gagal menjalankan analisis AI: ' . $e->getMessage());
        }

        $competitor = $result['competitor'];
        $geo = $result['geodemografi'];
        $market = $result['market_potential'];

        $analysis = MarketAnalysis::create([
            'umkm_id' => $profile->id,
            'location_query' => $request->location_query,
            'latitude' => $lat,
            'longitude' => $lng,
            'radius_km' => $request->radius_km,
            'market_fit_score' => (int) round($market['score'] * 100),
            'demographic_data' => [
                'population' => $geo['population_estimate'],
                'density' => $this->densityLabel($geo['population_density_per_km2']),
                'economic_indicator' => $geo['economic_indicator'],
                'dominant_consumer_segment' => $geo['dominant_consumer_segment'],
            ],
            'analysis_data' => [
                'category' => $request->category,
                'competition_level' => $competitor['competition_level'],
                'competition_score' => $competitor['competition_score'],
                'competition_density_per_km2' => $competitor['competition_density_per_km2'],
                'market_potential_label' => $market['label'],
                'market_potential_narrative' => $market['narrative'],
                'data_source' => $competitor['data_source'],
                'source' => 'ai-sipasar-python',
            ],
            'status' => 'completed',
        ]);

        Demographic::create([
            'umkm_id' => $profile->id,
            'analysis_id' => $analysis->id,
            'area_name' => $geo['area_name'] ?: $request->location_query,
            'population_data' => ['total' => $geo['population_estimate']],
            'income_data' => [],
            'age_distribution' => [],
            'data_source' => 'bps',
        ]);

        // Bulk insert — a per-row Competitor::create() loop over 50-100+ competitors
        // (common for dense areas) does that many individual round trips to Neon and
        // can blow past PHP's execution time limit on its own.
        $now = now();
        $rows = array_map(fn ($comp) => [
            'id' => Str::orderedUuid()->toString(),
            'analysis_id' => $analysis->id,
            'name' => $comp['name'],
            'business_type' => $request->category,
            'rating' => $comp['rating'] ?? 0.0,
            'review_count' => 0,
            'address' => $comp['address'] ?? '',
            'latitude' => $comp['latitude'],
            'longitude' => $comp['longitude'],
            'scraped_data' => json_encode([
                'place_id' => $comp['place_id'] ?? '',
                'source' => $comp['source'] ?? '',
                'maps_uri' => $comp['maps_uri'] ?? '',
                'distance_meters' => $comp['distance_m'] ?? null,
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ], $competitor['competitors']);

        if (! empty($rows)) {
            Competitor::insert($rows);
        }

        return redirect()->route('sipasar.results', $analysis->id)->with('success', 'Riset pasar berhasil dijalankan (AI-SiPasar Python)!');
    }

    private function densityLabel(float $densityPerKm2): string
    {
        return match (true) {
            $densityPerKm2 >= 15000 => 'Sangat Tinggi',
            $densityPerKm2 >= 8000 => 'Tinggi',
            $densityPerKm2 >= 3000 => 'Sedang',
            default => 'Rendah',
        };
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
    public function history(Request $request): View
    {
        $profile = $request->get('active_umkm') ?? UmkmProfile::where('user_id', Auth::id())->firstOrFail();

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
