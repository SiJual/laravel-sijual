<?php

namespace App\Http\Middleware;

use App\Services\Supabase\SupabaseAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupabaseAuth
{
    public function __construct(private SupabaseAuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = session('supabase_access_token');

        if (!$token) {
            return redirect()->route('login');
        }

        $user = $this->auth->getUser($token);

        if (!$user) {
            session()->forget(['supabase_access_token', 'supabase_refresh_token', 'supabase_user']);
            return redirect()->route('login')->with('error', 'Sesi Anda telah berakhir. Silakan masuk kembali.');
        }

        view()->share('authUser', $user);
        $request->merge(['supabase_user' => $user]);

        return $next($request);
    }
}
