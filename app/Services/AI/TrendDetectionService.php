<?php

namespace App\Services\AI;

use App\Services\AI\GeminiService;
use Illuminate\Support\Facades\Log;

class TrendDetectionService
{
    public function __construct(private GeminiService $gemini) {}

    /**
     * Identify emerging trends based on competitor POIs and demographic data.
     * 
     * @param array $competitors Array of competitor data (e.g. types, ratings)
     * @param array $demographics Demographics data of the area
     * @return array List of trend insights
     */
    public function detectTrends(array $competitors, array $demographics): array
    {
        $prompt = "Based on the following local competitors and demographics data, identify 3-5 emerging business trends or market gaps in the area. Return a JSON array of strings, where each string is a concise trend insight in Bahasa Indonesia.\n\nCompetitors:\n" . json_encode($competitors) . "\n\nDemographics:\n" . json_encode($demographics);

        try {
            $response = $this->gemini->ask($prompt);
            
            // Clean up Markdown code blocks if Gemini returns them
            $response = preg_replace('/```json\s*/', '', $response);
            $response = preg_replace('/```\s*/', '', $response);
            
            $trends = json_decode($response, true);

            if (is_array($trends)) {
                return $trends;
            }

            Log::warning('TrendDetectionService: Failed to parse Gemini response.', ['response' => $response]);
            return ["Belum ada tren spesifik yang terdeteksi di area ini."];

        } catch (\Exception $e) {
            Log::error('TrendDetectionService: Error detecting trends.', ['error' => $e->getMessage()]);
            return ["Gagal mendeteksi tren karena kendala sistem."];
        }
    }
}
