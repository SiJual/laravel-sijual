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
        $codeVerifier = bin2hex(random_bytes(32));
        session(['supabase_code_verifier' => $codeVerifier]);
        
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        
        $redirectUrl = route('auth.google.callback');
        return redirect()->away($this->auth->getGoogleOAuthUrl($redirectUrl, $codeChallenge));
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $codeVerifier = session('supabase_code_verifier');

        if (!$code || !$codeVerifier) {
            return redirect()->route('login')->with('error', 'Autentikasi Google dibatalkan atau sesi kadaluarsa.');
        }

        try {
            $result = $this->auth->exchangeCodeForSession($code, $codeVerifier);

            if (isset($result['user'])) {
                $user = User::firstOrCreate(
                    ['id' => $result['user']['id']],
                    [
                        'email' => $result['user']['email'],
                        'full_name' => $result['user']['user_metadata']['full_name'] ?? $result['user']['email'],
                        'avatar_url' => $result['user']['user_metadata']['avatar_url'] ?? null,
                        'role' => 'owner',
                    ]
                );
                
                \Illuminate\Support\Facades\Auth::login($user);
            }

            session([
                'supabase_access_token' => $result['access_token'],
                'supabase_refresh_token' => $result['refresh_token'],
                'supabase_user' => $result['user'],
            ]);

            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Google Auth Error: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Gagal masuk dengan Google. Error: ' . $e->getMessage());
        }
    }
}
