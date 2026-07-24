<?php

namespace App\Services\Supabase;

use Illuminate\Support\Facades\Http;

class SupabaseAuthService
{
    private string $url;
    private string $key;

    public function __construct()
    {
        $this->url = config('supabase.url', '');
        $this->key = config('supabase.key', '');
    }

    /**
     * Sign in dengan email + password.
     */
    public function signInWithPassword(string $email, string $password): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/token?grant_type=password", [
            'email' => $email,
            'password' => $password,
        ]);

        if ($response->failed()) {
            throw new \Exception($response->json('error_description', 'Email atau kata sandi tidak sesuai.'));
        }

        return $response->json();
    }

    /**
     * Sign up pengguna baru dengan email + password.
     */
    public function signUp(string $email, string $password, array $metadata = []): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/signup", [
            'email' => $email,
            'password' => $password,
            'data' => $metadata,
        ]);

        if ($response->failed()) {
            throw new \Exception($response->json('msg') ?? $response->json('error_description', 'Registrasi gagal.'));
        }

        return $response->json();
    }

    /**
     * Dapatkan detail user berdasarkan Supabase Access Token.
     */
    public function getUser(string $accessToken): ?array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$accessToken}",
        ])->get("{$this->url}/auth/v1/user");

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Sign out user dari Supabase session.
     */
    public function signOut(string $accessToken): void
    {
        Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$accessToken}",
        ])->post("{$this->url}/auth/v1/logout");
    }

    /**
     * Kirim email pemulihan kata sandi.
     */
    public function resetPassword(string $email): void
    {
        Http::withHeaders([
            'apikey' => $this->key,
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/recover", [
            'email' => $email,
        ]);
    }

    /**
     * Dapatkan URL untuk OAuth Google.
     */
    public function getGoogleOAuthUrl(string $redirectTo, string $codeChallenge): string
    {
        return "{$this->url}/auth/v1/authorize?provider=google&redirect_to=" . urlencode($redirectTo) . "&code_challenge_method=s256&code_challenge=" . urlencode($codeChallenge);
    }

    /**
     * Exchange auth code untuk session token.
     */
    public function exchangeCodeForSession(string $code, string $codeVerifier): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/token?grant_type=pkce", [
            'auth_code' => $code,
            'code_verifier' => $codeVerifier,
        ]);

        if ($response->failed()) {
            throw new \Exception($response->json('error_description', 'Gagal memproses autentikasi Google.'));
        }

        return $response->json();
    }
}
