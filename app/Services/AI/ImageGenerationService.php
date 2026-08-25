<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageGenerationService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('ai.openai.api_key', '');
        $this->model = config('ai.openai.image_model', 'gpt-image-1');
    }

    /**
     * Generate a marketing image via OpenAI's Images API and store it locally.
     * gpt-image-1 returns base64 (no hosted URL like dall-e-3), so we decode
     * and save to the public disk ourselves.
     */
    public function generateImage(string $prompt): string
    {
        if (empty($this->apiKey)) {
            Log::warning('ImageGenerationService: OPENAI_API_KEY belum dikonfigurasi.');

            return $this->fallbackImage();
        }

        try {
            $response = Http::timeout(90)
                ->withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
                ->post('https://api.openai.com/v1/images/generations', [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'size' => '1024x1024',
                    'n' => 1,
                ]);

            if ($response->successful()) {
                $b64 = $response->json('data.0.b64_json');
                $url = $response->json('data.0.url');

                if ($b64) {
                    return $this->storeBase64Image($b64);
                }
                if ($url) {
                    return $url;
                }
            }

            Log::error('ImageGenerationService: OpenAI image API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ImageGenerationService: exception', ['message' => $e->getMessage()]);
        }

        return $this->fallbackImage();
    }

    private function storeBase64Image(string $b64): string
    {
        $filename = 'sipromo/' . Str::uuid() . '.png';
        Storage::disk('public')->put($filename, base64_decode($b64));

        return Storage::disk('public')->url($filename);
    }

    private function fallbackImage(): string
    {
        return 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=1024&q=80';
    }
}
