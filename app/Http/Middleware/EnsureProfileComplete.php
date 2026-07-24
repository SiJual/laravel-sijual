<?php

namespace App\Http\Middleware;

use App\Models\UmkmProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = session('supabase_user');

        if (!$user) {
            return redirect()->route('login');
        }

        $profile = UmkmProfile::where('user_id', $user['id'])->first();

        if (!$profile || $profile->profile_completeness < 100) {
            return redirect()->route('onboarding')->with('warning', 'Silakan lengkapi profil usaha Anda terlebih dahulu.');
        }

        view()->share('activeUmkm', $profile);
        $request->merge(['active_umkm' => $profile]);

        return $next($request);
    }
}
