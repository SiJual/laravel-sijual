<?php

namespace App\Services\AI;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('ai.gemini.api_key', '');
        $this->model = config('ai.gemini.model', 'gemini-2.5-flash');
    }

    /**
     * Generate content using Gemini API with OpenAI fallback.
     */
    public function generateContent(string $prompt, array $options = []): string
    {
        try {
            return $this->callGemini($prompt, $options);
        } catch (\Exception $e) {
            Log::warning('Gemini call failed, falling back to OpenAI: ' . $e->getMessage());
            return app(OpenAIFallbackService::class)->generate($prompt, $options);
        }
    }

    /**
     * Transcribe and categorize audio directly using Gemini 2.5 Flash Multimodal.
     *
     * @param UploadedFile $audioFile
     * @return array{type: string, amount: int, description: string, category: string, transcribed_text: string, confidence: float}
     */
    public function transcribeAndCategorizeAudio(UploadedFile $audioFile): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API Key belum dikonfigurasi.');
        }

        $audioContent = file_get_contents($audioFile->getRealPath());
        $base64Audio = base64_encode($audioContent);
        
        $mimeType = $audioFile->getMimeType();
        if (empty($mimeType) || $mimeType === 'application/octet-stream') {
            $ext = strtolower($audioFile->getClientOriginalExtension());
            $mimeType = match ($ext) {
                'mp3' => 'audio/mp3',
                'wav' => 'audio/wav',
                'ogg' => 'audio/ogg',
                'm4a' => 'audio/m4a',
                default => 'audio/webm',
            };
        }

        $prompt = <<<PROMPT
Anda adalah asisten pencatatan keuangan pintar SiKas untuk UMKM Indonesia.
Dengarkan rekaman suara berikut dengan seksama dalam Bahasa Indonesia atau bahasa daerah.
Tugas Anda:
1. Transkripsikan kalimat yang diucapkan pembicara secara utuh.
2. Analisis transaksi dan ekstraksi ke format JSON valid berikut:

{
  "transcribed_text": "kalimat lengkap yang diucapkan pengguna",
  "type": "income" atau "expense",
  "amount": nominal angka integer (tanpa titik atau Rp),
  "description": "deskripsi singkat transaksi (misal: Makan Siang Soto Lamongan)",
  "category": "kategori relevan (misal: Makanan & Minuman, Penjualan Produk, Bahan Baku, Operasional, Transportasi, Listrik & Air, dll)",
  "confidence": 0.95
}

Kembalikan HANYA format JSON valid tanpa tanda markdown (backticks).
PROMPT;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(30)->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64Audio,
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        if ($response->failed()) {
            throw new \Exception('Gemini Audio API error: ' . $response->body());
        }

        $data = $response->json();
        $rawResult = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $cleanJson = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/i', '$1', trim($rawResult));
        $parsed = json_decode($cleanJson, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            return [
                'type' => in_array($parsed['type'] ?? '', ['income', 'expense']) ? $parsed['type'] : 'expense',
                'amount' => (int) ($parsed['amount'] ?? 0),
                'description' => (string) ($parsed['description'] ?? 'Transaksi Suara'),
                'category' => (string) ($parsed['category'] ?? 'Lain-lain'),
                'transcribed_text' => (string) ($parsed['transcribed_text'] ?? ''),
                'confidence' => (float) ($parsed['confidence'] ?? 0.9),
            ];
        }

        throw new \Exception('Gagal mengekstrak transaksi dari audio: Response JSON tidak valid.');
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
  "confidence": 0.95
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
            throw new \Exception('Gagal menganalisis transaksi menggunakan AI: ' . $e->getMessage());
        }

        throw new \Exception('Gagal menganalisis transaksi menggunakan AI: Format response tidak valid.');
    }

    private function callGemini(string $prompt, array $options = []): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API Key belum dikonfigurasi.');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(15)->post($url, [
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
