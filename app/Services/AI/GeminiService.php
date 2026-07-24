<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class GeminiService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('ai.gemini.api_key', '');
        $this->model = config('ai.gemini.model', 'gemini-2.0-flash');
    }

    /**
     * Generate content using Gemini API with OpenAI fallback.
     */
    public function generateContent(string $prompt, array $options = []): string
    {
        try {
            return $this->callGemini($prompt, $options);
        } catch (\Exception $e) {
            return app(OpenAIFallbackService::class)->generate($prompt, $options);
        }
    }

    /**
     * Categorize voice or text input into structured transaction data.
     *
     * @param string $text
     * @return array{type: string, amount: int, description: string, category: string, confidence: float}
     */
    public function categorizeTransaction(string $text): array
    {
        $prompt = <<<PROMPT
Anda adalah asisten keuangan pintar untuk UMKM Indonesia.
Analisis teks transaksi berikut dan kembalikan HANYA format JSON valid tanpa markdown/backticks:
Teks: "{$text}"

Format JSON yang wajib dikembalikan:
{
  "type": "income" atau "expense",
  "amount": jumlah uang dalam integer (tanpa Rp atau titik/koma),
  "description": "deskripsi singkat transaksi",
  "category": "Kategori transaksi (contoh: Penjualan, Bahan Baku, Operasional, Gaji, dll)",
  "confidence": angka float antara 0.0 - 1.0
}
PROMPT;

        try {
            $rawResult = $this->generateContent($prompt);
            $cleanJson = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/i', '$1', trim($rawResult));
            $parsed = json_decode($cleanJson, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return [
                    'type' => in_array($parsed['type'] ?? '', ['income', 'expense']) ? $parsed['type'] : 'expense',
                    'amount' => (int) ($parsed['amount'] ?? 0),
                    'description' => (string) ($parsed['description'] ?? $text),
                    'category' => (string) ($parsed['category'] ?? 'Lain-lain'),
                    'confidence' => (float) ($parsed['confidence'] ?? 0.8),
                ];
            }
        } catch (\Throwable $e) {
            // Fallback safe default
        }

        return [
            'type' => 'expense',
            'amount' => 0,
            'description' => $text,
            'category' => 'Lain-lain',
            'confidence' => 0.0,
        ];
    }

    private function callGemini(string $prompt, array $options = []): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API Key belum dikonfigurasi.');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ]);

        if ($response->failed()) {
            throw new \Exception('Gemini API error: ' . $response->body());
        }

        $data = $response->json();
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }
}
