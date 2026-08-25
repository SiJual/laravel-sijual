<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Http;

class SiPasarBridgeService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.sipasar_bridge.base_url'), '/');
    }

    /**
     * Liveness check against the Python sidecar.
     */
    public function isHealthy(): bool
    {
        try {
            return Http::timeout(3)->get("{$this->baseUrl}/api/v1/health/live")->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Call the real AI-SiPasar analysis pipeline (Python sidecar).
     *
     * @throws \Exception on non-2xx response or unreachable sidecar
     */
    public function analyze(string $businessProfileId, float $lat, float $lon, string $category, int $radiusMeters): array
    {
        $response = Http::timeout(45)->post("{$this->baseUrl}/v1/analysis/run", [
            'business_profile_id' => $businessProfileId,
            'latitude' => $lat,
            'longitude' => $lon,
            'category' => $category,
            'radius_meters' => $this->snapRadius($radiusMeters),
        ]);

        if ($response->failed()) {
            throw new \Exception('AI-SiPasar sidecar error: ' . $response->status() . ' ' . $response->body());
        }

        return $response->json();
    }

    /**
     * The Python service only accepts a fixed radius enum (in meters).
     */
    private function snapRadius(int $radiusMeters): int
    {
        $allowed = [500, 1000, 3000, 5000, 10000];

        return collect($allowed)->sort(fn ($a, $b) => abs($a - $radiusMeters) <=> abs($b - $radiusMeters))->first();
    }
}
