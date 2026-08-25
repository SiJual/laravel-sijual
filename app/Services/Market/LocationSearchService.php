<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocationSearchService
{
    /**
     * Free-text place search — matches specific businesses/POIs, not just
     * administrative areas. Google Places Text Search first (if configured,
     * best POI coverage), OSM Nominatim fallback (free, no key needed).
     *
     * @return array<int, array{name:string,address:string,lat:float,lng:float}>
     */
    public function search(string $query): array
    {
        $apiKey = config('services.google_places.key');

        if ($apiKey) {
            try {
                $results = $this->googleTextSearch($query, $apiKey);
                if (! empty($results)) {
                    return $results;
                }
            } catch (\Throwable $e) {
                Log::warning('Google Places text search unavailable, falling back to Nominatim: ' . $e->getMessage());
            }
        }

        return $this->nominatimSearch($query);
    }

    private function googleTextSearch(string $query, string $apiKey): array
    {
        $response = Http::timeout(8)
            ->withHeaders([
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => 'places.displayName,places.formattedAddress,places.location',
                'Content-Type' => 'application/json',
            ])
            ->post('https://places.googleapis.com/v1/places:searchText', [
                'textQuery' => $query,
                'languageCode' => 'id',
                'regionCode' => 'ID',
            ]);

        if ($response->failed()) {
            throw new \Exception('Google Places text search error: ' . $response->body());
        }

        $results = [];
        foreach ($response->json('places', []) as $place) {
            $location = $place['location'] ?? [];
            if (! isset($location['latitude'], $location['longitude'])) {
                continue;
            }

            $results[] = [
                'name' => $place['displayName']['text'] ?? $query,
                'address' => $place['formattedAddress'] ?? '',
                'lat' => (float) $location['latitude'],
                'lng' => (float) $location['longitude'],
            ];
        }

        return array_slice($results, 0, 5);
    }

    private function nominatimSearch(string $query): array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'SiJual-SiPasar/1.0 location-search'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 5,
                    'countrycodes' => 'id',
                ]);

            if ($response->failed()) {
                return [];
            }

            $results = [];
            foreach ($response->json() ?? [] as $place) {
                if (! isset($place['lat'], $place['lon'])) {
                    continue;
                }

                $results[] = [
                    'name' => $place['display_name'] ?? $query,
                    'address' => $place['display_name'] ?? '',
                    'lat' => (float) $place['lat'],
                    'lng' => (float) $place['lon'],
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning('Nominatim search failed: ' . $e->getMessage());

            return [];
        }
    }
}
