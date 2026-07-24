<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MetaOAuthService
{
    private string $graphApiUrl = 'https://graph.facebook.com/v19.0';
    
    /**
     * Generate OAuth Login URL for Facebook/Instagram integration.
     */
    public function getLoginUrl(): string
    {
        $appId = config('services.meta.client_id');
        $redirectUri = urlencode(route('auth.meta.callback'));
        $scopes = 'instagram_basic,instagram_content_publish,pages_show_list,pages_read_engagement';

        return "https://www.facebook.com/v19.0/dialog/oauth?client_id={$appId}&redirect_uri={$redirectUri}&scope={$scopes}";
    }

    /**
     * Exchange OAuth code for a long-lived access token.
     */
    public function exchangeCodeForToken(string $code): ?string
    {
        try {
            $response = Http::get("{$this->graphApiUrl}/oauth/access_token", [
                'client_id' => config('services.meta.client_id'),
                'client_secret' => config('services.meta.client_secret'),
                'redirect_uri' => route('auth.meta.callback'),
                'code' => $code,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }

            Log::error('MetaOAuth: Failed to exchange code', ['response' => $response->body()]);
            return null;
        } catch (\Exception $e) {
            Log::error('MetaOAuth: Exception during token exchange', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Publish an image to an Instagram Business Account.
     */
    public function publishToInstagram(string $igUserId, string $accessToken, string $imageUrl, string $caption): bool
    {
        try {
            // Step 1: Create media container
            $containerResponse = Http::post("{$this->graphApiUrl}/{$igUserId}/media", [
                'image_url' => $imageUrl,
                'caption' => $caption,
                'access_token' => $accessToken,
            ]);

            if (!$containerResponse->successful()) {
                Log::error('MetaOAuth: Failed to create media container', ['response' => $containerResponse->body()]);
                return false;
            }

            $creationId = $containerResponse->json('id');

            // Step 2: Publish the media container
            $publishResponse = Http::post("{$this->graphApiUrl}/{$igUserId}/media_publish", [
                'creation_id' => $creationId,
                'access_token' => $accessToken,
            ]);

            if ($publishResponse->successful()) {
                return true;
            }

            Log::error('MetaOAuth: Failed to publish media', ['response' => $publishResponse->body()]);
            return false;

        } catch (\Exception $e) {
            Log::error('MetaOAuth: Exception during Instagram publish', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
