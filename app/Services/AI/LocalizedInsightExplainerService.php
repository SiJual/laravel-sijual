<?php

namespace App\Services\AI;

use App\Services\AI\GeminiService;
use Illuminate\Support\Facades\Log;

class LocalizedInsightExplainerService
{
    public function __construct(private GeminiService $gemini) {}

    /**
     * Explain market insights in simple, easy-to-understand Bahasa Indonesia.
     * 
     * @param string $rawInsight Technical or raw insight data
     * @param string $businessType The type of business (e.g. F&B, Retail)
     * @return string Simplified explanation
     */
    public function explain(string $rawInsight, string $businessType): string
    {
        $prompt = "Jelaskan data pasar berikut kepada pemilik UMKM ($businessType) dengan bahasa Indonesia yang sangat sederhana, ramah, dan mudah dimengerti. Hindari jargon teknis.\n\nData:\n$rawInsight";

        try {
            $response = $this->gemini->ask($prompt);
            return trim($response);
        } catch (\Exception $e) {
            Log::error('LocalizedInsightExplainerService: Error explaining insight.', ['error' => $e->getMessage()]);
            return "Saat ini kami tidak dapat menyederhanakan data pasar ini.";
        }
    }
}
