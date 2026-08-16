<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak memiliki hak akses untuk tindakan ini.');
        }

        return $next($request);
    }
}
