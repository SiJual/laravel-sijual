<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class FluxSchnellService
{
    private string $endpoint;

    public function __construct()
    {
        $this->endpoint = config('ai.flux.endpoint', 'http://localhost:8080');
    }

    /**
     * Generate marketing image using Flux Schnell.
     *
     * @param string $prompt
     * @return string Image URL
     */
    public function generateImage(string $prompt): string
    {
        try {
            $response = Http::timeout(10)->post("{$this->endpoint}/generate", [
                'prompt' => $prompt,
                'width' => 1024,
                'height' => 1024,
                'steps' => 4,
            ]);

            if ($response->successful() && isset($response->json()['image_url'])) {
                return $response->json()['image_url'];
            }
        } catch (\Throwable $e) {
            // Fallback placeholder image URL
        }

        return "https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=1024&q=80";
    }
}
