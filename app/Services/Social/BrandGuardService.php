<?php

namespace App\Services\Social;

use App\Services\AI\GeminiService;
use Illuminate\Support\Facades\Log;

class BrandGuardService
{
    public function __construct(private GeminiService $gemini) {}

    /**
     * Validate generated caption and content against basic brand guidelines.
     * Ensures there are no offensive words or off-brand tone.
     * 
     * @param string $caption
     * @param string $brandTone (e.g. "Formal", "Santai", "Profesional")
     * @return array ['is_safe' => bool, 'feedback' => string]
     */
    public function validateCaption(string $caption, string $brandTone): array
    {
        $prompt = "Tolong periksa caption sosial media berikut. Pastikan bahasanya aman (tidak menyinggung SARA atau hal negatif), dan sesuai dengan tone merek yang '$brandTone'. Jawab HANYA dalam format JSON: {\"is_safe\": true/false, \"feedback\": \"Alasan singkat kenapa aman/tidak aman\"}\n\nCaption:\n$caption";

        try {
            $response = $this->gemini->ask($prompt);
            
            // Clean up Markdown formatting
            $response = preg_replace('/```json\s*/', '', $response);
            $response = preg_replace('/```\s*/', '', $response);

            $result = json_decode($response, true);

            if (is_array($result) && isset($result['is_safe'])) {
                return $result;
            }

            Log::warning('BrandGuardService: Failed to parse Gemini response.', ['response' => $response]);
            return ['is_safe' => true, 'feedback' => 'Validation bypass due to parsing error.'];

        } catch (\Exception $e) {
            Log::error('BrandGuardService: Error validating caption.', ['error' => $e->getMessage()]);
            // Default to safe if AI fails, so we don't block users unnecessarily
            return ['is_safe' => true, 'feedback' => 'Validasi otomatis sedang tidak tersedia.'];
        }
    }
}
