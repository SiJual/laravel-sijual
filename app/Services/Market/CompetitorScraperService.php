<?php

namespace App\Services\Market;

class CompetitorScraperService
{
    /**
     * Discover & scrape nearby competitors within radius.
     *
     * @param string $locationQuery
     * @param float $lat
     * @param float $lng
     * @param float $radiusKm
     * @return array<int, array{name: string, business_type: string, rating: float, review_count: int, sentiment: string, address: string, latitude: float, longitude: float}>
     */
    public function discoverCompetitors(string $locationQuery, float $lat, float $lng, float $radiusKm = 1.0): array
    {
        // Sample competitor data based on location
        return [
            [
                'name' => 'Kedai Kopi Kenangan ' . $locationQuery,
                'business_type' => 'F&B / Coffee',
                'rating' => 4.6,
                'review_count' => 128,
                'sentiment' => 'positive',
                'address' => 'Jl. Kebayoran Baru No. 45',
                'latitude' => $lat + 0.002,
                'longitude' => $lng + 0.003,
            ],
            [
                'name' => 'Warung Kopi Janji Jiwa',
                'business_type' => 'F&B / Coffee',
                'rating' => 4.4,
                'review_count' => 84,
                'sentiment' => 'positive',
                'address' => 'Jl. Kyai Maja No. 8',
                'latitude' => $lat - 0.001,
                'longitude' => $lng + 0.002,
            ],
            [
                'name' => 'Warkop Berkah Nusantara',
                'business_type' => 'F&B / Traditional',
                'rating' => 4.2,
                'review_count' => 35,
                'sentiment' => 'neutral',
                'address' => 'Jl. Bulungan No. 12',
                'latitude' => $lat + 0.003,
                'longitude' => $lng - 0.002,
            ],
        ];
    }
}
