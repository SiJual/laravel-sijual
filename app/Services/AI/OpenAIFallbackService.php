<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class OpenAIFallbackService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('ai.openai.api_key', '');
        $this->model = config('ai.openai.model', 'gpt-4o-mini');
    }

    /**
     * Fallback generation using OpenAI Chat Completions API.
     */
    public function generate(string $prompt, array $options = []): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API Key belum dikonfigurasi.');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if ($response->failed()) {
            throw new \Exception('OpenAI API error: ' . $response->body());
        }

        $data = $response->json();
        return $data['choices'][0]['message']['content'] ?? '';
    }
}
