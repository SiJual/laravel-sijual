<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompetitorScraperService
{
    /**
     * Synthesize likely competitors using OpenAI Structured Outputs
     * based on demographic context.
     *
     * @param string $locationQuery
     * @param float $lat
     * @param float $lng
     * @param float $radiusKm
     * @param array $demographics
     * @param string $businessType
     * @return array
     */
    public function discoverCompetitors(string $locationQuery, float $lat, float $lng, float $radiusKm = 1.0, array $demographics = [], string $businessType = 'F&B'): array
    {
        $apiKey = config('ai.openai.api_key');
        $model = config('ai.openai.model', 'gpt-4o-mini');
        
        $commercial = $demographics['poi_summary']['commercial_nodes'] ?? 50;
        $residential = $demographics['poi_summary']['residential_ways'] ?? 200;
        $density = $demographics['density'] ?? 'Sedang';
        $maxMeters = (int) ($radiusKm * 1000);

        $prompt = <<<PROMPT
Anda adalah AI Analis Bisnis. Berdasarkan area {$locationQuery} dengan radius {$radiusKm} km, 
dan data lapangan aktual: {$commercial} titik komersial, {$residential} area pemukiman (Kepadatan: {$density}).
Buat daftar 5 hingga 8 kompetitor hipotetis/riil di bidang {$businessType}, lengkap dengan nama, kategori spesifik, rating (1-5, float), jumlah ulasan, dan sentimen ulasan.
Tentukan juga distance_in_meters (jarak dari pusat, maksimal {$maxMeters} meter) dan bearing_degrees (0-360 derajat arah kompas).
PROMPT;

        $schema = [
            "type" => "object",
            "properties" => [
                "competitors" => [
                    "type" => "array",
                    "items" => [
                        "type" => "object",
                        "properties" => [
                            "name" => ["type" => "string"],
                            "business_type" => ["type" => "string"],
                            "rating" => ["type" => "number"],
                            "review_count" => ["type" => "integer"],
                            "sentiment" => ["type" => "string", "enum" => ["positive", "neutral", "negative"]],
                            "distance_in_meters" => ["type" => "integer"],
                            "bearing_degrees" => ["type" => "integer"]
                        ],
                        "required" => ["name", "business_type", "rating", "review_count", "sentiment", "distance_in_meters", "bearing_degrees"],
                        "additionalProperties" => false
                    ]
                ]
            ],
            "required" => ["competitors"],
            "additionalProperties" => false
        ];

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a professional business analyst AI. Answer strictly in Indonesian.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'competitor_list',
                    'strict' => true,
                    'schema' => $schema
                ]
            ],
            'temperature' => 0.7
        ];

        $retryCount = 0;
        $maxRetries = 1;

        while ($retryCount <= $maxRetries) {
            try {
                $response = Http::withToken($apiKey)
                    ->timeout(15)
                    ->post('https://api.openai.com/v1/chat/completions', $payload);

                if ($response->successful()) {
                    $jsonStr = $response->json('choices.0.message.content');
                    $data = json_decode($jsonStr, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['competitors'])) {
                        return $data['competitors'];
                    }
                }
            } catch (\Throwable $e) {
                Log::error('OpenAI Synthesis Error: ' . $e->getMessage());
            }
            $retryCount++;
        }

        // Fallback if OpenAI fails completely
        return [
            [
                'name' => 'Kopi Kenangan (Estimasi)',
                'business_type' => 'F&B / Coffee',
                'rating' => 4.5,
                'review_count' => 120,
                'sentiment' => 'positive',
                'distance_in_meters' => 500,
                'bearing_degrees' => 90
            ],
            [
                'name' => 'Warkop Berkah (Estimasi)',
                'business_type' => 'F&B / Traditional',
                'rating' => 4.1,
                'review_count' => 45,
                'sentiment' => 'neutral',
                'distance_in_meters' => 800,
                'bearing_degrees' => 270
            ]
        ];
    }
}
