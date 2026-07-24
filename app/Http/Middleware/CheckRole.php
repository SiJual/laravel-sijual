<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userSession = session('supabase_user');

        if (!$userSession) {
            return redirect()->route('login');
        }

        $user = User::find($userSession['id']);
        $role = $user ? $user->role : 'owner';

        if (!in_array($role, $roles, true)) {
            abort(403, 'Anda tidak memiliki hak akses untuk tindakan ini.');
        }

        return $next($request);
    }
}
