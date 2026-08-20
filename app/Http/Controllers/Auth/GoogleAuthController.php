<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(private JwtService $jwt) {}

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'full_name' => $googleUser->getName() ?? $googleUser->getEmail(),
                    'avatar_url' => $googleUser->getAvatar(),
                    'role' => 'owner',
                    'password' => Hash::make(Str::random(32)),
                ]
            );

            $cookie = $this->jwt->makeCookie($user);

            return redirect()->route('dashboard')->withCookie($cookie);
        } catch (\Exception $e) {
            Log::error('Google Auth Error: ' . $e->getMessage());

            return redirect()->route('login')->with('error', 'Gagal masuk dengan Google. Silakan coba lagi.');
        }
    }
}
