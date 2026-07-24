<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Supabase\SupabaseAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleAuthController extends Controller
{
    public function __construct(private SupabaseAuthService $auth) {}

    public function redirect(): RedirectResponse
    {
        $redirectUrl = route('auth.google.callback');
        return redirect()->away($this->auth->getGoogleOAuthUrl($redirectUrl));
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');

        if (!$code) {
            return redirect()->route('login')->with('error', 'Autentikasi Google dibatalkan.');
        }

        try {
            $result = $this->auth->exchangeCodeForSession($code);

            if (isset($result['user'])) {
                User::firstOrCreate(
                    ['id' => $result['user']['id']],
                    [
                        'email' => $result['user']['email'],
                        'full_name' => $result['user']['user_metadata']['full_name'] ?? $result['user']['email'],
                        'avatar_url' => $result['user']['user_metadata']['avatar_url'] ?? null,
                        'role' => 'owner',
                    ]
                );
            }

            session([
                'supabase_access_token' => $result['access_token'],
                'supabase_refresh_token' => $result['refresh_token'],
                'supabase_user' => $result['user'],
            ]);

            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Gagal masuk dengan Google. Silakan coba lagi.');
        }
    }
}
