<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Supabase\SupabaseAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(private SupabaseAuthService $auth) {}

    public function show(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ], [
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        try {
            $result = $this->auth->signUp($request->email, $request->password, [
                'full_name' => $request->full_name,
            ]);

            if (isset($result['user'])) {
                // User is automatically created in public.users via Supabase DB trigger on_auth_user_created

                session([
                    'supabase_access_token' => $result['access_token'] ?? null,
                    'supabase_refresh_token' => $result['refresh_token'] ?? null,
                    'supabase_user' => $result['user'],
                ]);
            }

            return redirect()->route('onboarding')->with('success', 'Akun berhasil dibuat. Silakan lengkapi profil usaha Anda.');
        } catch (\Exception $e) {
            return back()->withErrors([
                'email' => $e->getMessage(),
            ])->withInput($request->except('password', 'password_confirmation'));
        }
    }
}
