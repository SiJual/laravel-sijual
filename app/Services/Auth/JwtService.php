<?php

namespace App\Services\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cookie as CookieFacade;
use Symfony\Component\HttpFoundation\Cookie;

class JwtService
{
    public function encode(User $user, int $ttlMinutes): string
    {
        $now = time();

        return JWT::encode([
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $now + ($ttlMinutes * 60),
        ], config('jwt.secret'), 'HS256');
    }

    public function decode(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function makeCookie(User $user, bool $remember = false): Cookie
    {
        $ttlMinutes = $remember ? config('jwt.remember_ttl') : config('jwt.ttl');
        $token = $this->encode($user, $ttlMinutes);

        return CookieFacade::make(
            config('jwt.cookie'),
            $token,
            $ttlMinutes,
            '/',
            null,
            app()->environment('production'),
            true,
            false,
            'lax'
        );
    }

    public function forgetCookie(): Cookie
    {
        return CookieFacade::forget(config('jwt.cookie'));
    }
}
