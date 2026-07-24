<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class SupabaseUserProvider implements UserProvider
{
    public function retrieveById($identifier)
    {
        return User::find($identifier);
    }

    public function retrieveByToken($identifier, $token)
    {
        return null; // We use session, not remember_token
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        // Not implemented
    }

    public function retrieveByCredentials(array $credentials)
    {
        // Handled directly by Supabase Auth Service
        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        // Handled directly by Supabase Auth Service
        return false;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false)
    {
        // Not implemented for Supabase
    }
}
