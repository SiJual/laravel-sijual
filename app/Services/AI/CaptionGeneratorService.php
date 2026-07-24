<?php

namespace App\Services\AI;

class CaptionGeneratorService
{
    public function __construct(private GeminiService $gemini) {}

    /**
     * Generate marketing caption and hashtags.
     *
     * @param string $businessName
     * @param string $prompt
     * @param string $contentType
     * @return array{text: string, caption: string, hashtags: array<int, string>}
     */
    public function generate(string $businessName, string $prompt, string $contentType = 'social_media'): array
    {
        $aiPrompt = <<<PROMPT
Buatkan caption promosi menarik untuk usaha "{$businessName}" dengan topik: "{$prompt}".
Kembalikan HANYA format JSON valid tanpa markdown/backticks:
{
  "text": "Teks iklan / copy utama",
  "caption": "Caption Instagram menarik lengkap dengan emoji",
  "hashtags": ["#UMKMIndonesia", "#Promosi", "#Kuliner"]
}
PROMPT;

        try {
            $raw = $this->gemini->generateContent($aiPrompt);
            $clean = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/i', '$1', trim($raw));
            $parsed = json_decode($clean, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return [
                    'text' => (string) ($parsed['text'] ?? $prompt),
                    'caption' => (string) ($parsed['caption'] ?? $prompt),
                    'hashtags' => (array) ($parsed['hashtags'] ?? ['#UMKM', '#SiJual']),
                ];
            }
        } catch (\Throwable $e) {
            // Fallback
        }

        return [
            'text' => "Spesial dari {$businessName}! " . $prompt,
            'caption' => "Spesial dari {$businessName}! ✨ " . $prompt . "\n\nDapatkan promo menarik minggu ini! Jangan sampai kehabisan. 🛍️",
            'hashtags' => ['#UMKMIndonesia', '#' . str_replace(' ', '', $businessName), '#PromoNusantara'],
        ];
    }
}
