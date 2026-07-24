<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Supabase\SupabaseAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private SupabaseAuthService $auth) {}

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
        ]);

        try {
            $result = $this->auth->signInWithPassword($request->email, $request->password);

            // Sync user to local database if not exists
            if (isset($result['user'])) {
                $user = User::firstOrCreate(
                    ['id' => $result['user']['id']],
                    [
                        'email' => $result['user']['email'] ?? $request->email,
                        'full_name' => $result['user']['user_metadata']['full_name'] ?? null,
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
            return back()->withErrors([
                'email' => 'Email atau kata sandi salah. Silakan coba lagi.',
            ])->withInput(['email' => $request->email]);
        }
    }

    public function destroy(): RedirectResponse
    {
        $token = session('supabase_access_token');
        if ($token) {
            try {
                $this->auth->signOut($token);
            } catch (\Throwable $e) {
                // Ignore API failure to ensure local session is still flushed
            }
        }

        \Illuminate\Support\Facades\Auth::logout();
        session()->flush();
        return redirect()->route('login');
    }
}
