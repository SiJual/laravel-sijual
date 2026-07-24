<?php

namespace App\Services\AI;

use App\Services\AI\GeminiService;
use Illuminate\Support\Facades\Log;

class SentimentTaggingService
{
    public function __construct(private GeminiService $gemini) {}

    /**
     * Analyze a batch of competitor reviews and return sentiment tags.
     * 
     * @param array $reviews Array of review text strings
     * @return array Array mapped with 'positive', 'neutral', 'negative' labels
     */
    public function analyzeBatch(array $reviews): array
    {
        if (empty($reviews)) {
            return [];
        }

        $prompt = "Analyze the following customer reviews for competitors and classify each one as either 'positive', 'neutral', or 'negative'. Return a JSON array of strings exactly matching the order of the provided reviews.\n\nReviews:\n" . json_encode($reviews);

        try {
            $response = $this->gemini->ask($prompt);
            $tags = json_decode($response, true);

            if (is_array($tags) && count($tags) === count($reviews)) {
                return array_map('strtolower', $tags);
            }

            // Fallback if parsing fails
            Log::warning('SentimentTaggingService: Failed to parse Gemini response or count mismatch.', ['response' => $response]);
            return array_fill(0, count($reviews), 'neutral');

        } catch (\Exception $e) {
            Log::error('SentimentTaggingService: Error analyzing sentiment.', ['error' => $e->getMessage()]);
            return array_fill(0, count($reviews), 'neutral');
        }
    }
}
