<?php

namespace App\Services\AI;

class MarketFitScoreService
{
    public function __construct(private GeminiService $gemini) {}

    /**
     * Calculate 0-100 Market Fit Score based on competitor count, density, and demographic data.
     *
     * @param string $businessType
     * @param int $competitorCount
     * @param string $density
     * @return int
     */
    public function calculateScore(string $businessType, int $competitorCount, string $density = 'Tinggi'): int
    {
        $prompt = <<<PROMPT
Hitung Market Fit Score (0-100) untuk bisnis UMKM "{$businessType}" di area dengan kepadatannya "{$density}" dan jumlah kompetitor sejenis "{$competitorCount}".
Kembalikan HANYA satu angka integer antara 0 sampai 100 tanpa teks lain.
PROMPT;

        try {
            $response = $this->gemini->generateContent($prompt);
            $score = (int) filter_var($response, FILTER_SANITIZE_NUMBER_INT);
            if ($score >= 0 && $score <= 100) {
                return $score;
            }
        } catch (\Throwable $e) {
            // Fallback calculation
        }

        // Default heuristic score if AI fails
        $baseScore = 75;
        if ($competitorCount <= 2) {
            $baseScore = 88;
        } elseif ($competitorCount <= 5) {
            $baseScore = 78;
        } else {
            $baseScore = 65;
        }

        if ($density === 'Sangat Tinggi') $baseScore += 5;
        elseif ($density === 'Tinggi') $baseScore += 3;
        elseif ($density === 'Rendah') $baseScore -= 5;

        return min(100, max(0, $baseScore));
    }
}
