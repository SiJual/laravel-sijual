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
