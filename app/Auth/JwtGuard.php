<?php

namespace App\Auth;

use App\Services\Auth\JwtService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class JwtGuard implements Guard
{
    protected ?Authenticatable $user = null;
    protected bool $resolved = false;

    public function __construct(
        protected UserProvider $provider,
        protected Request $request,
        protected JwtService $jwt,
    ) {}

    public function check(): bool
    {
        return !is_null($this->user());
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?Authenticatable
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;

        $token = $this->request->cookie(config('jwt.cookie'));

        if (!$token) {
            return null;
        }

        $payload = $this->jwt->decode($token);

        if (!$payload || !isset($payload->sub)) {
            return null;
        }

        $this->user = $this->provider->retrieveById($payload->sub);

        return $this->user;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        return $user && $this->provider->validateCredentials($user, $credentials);
    }

    public function hasUser(): bool
    {
        return !is_null($this->user);
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;
        $this->resolved = true;

        return $this;
    }
}
