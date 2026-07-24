<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Cache;

class AnalysisCacheService
{
    /**
     * Cache TTL in seconds. Default 24 hours.
     */
    private const CACHE_TTL = 86400;

    /**
     * Fetch from cache or execute a callback to get data, storing the result.
     * 
     * @param string $key Unique cache key (e.g., location coordinates hash)
     * @param callable $callback The function to execute if cache misses
     * @param int|null $ttl Time to live in seconds
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        $ttl = $ttl ?? self::CACHE_TTL;

        return Cache::remember('sipasar_analysis_' . $key, $ttl, $callback);
    }

    /**
     * Invalidate specific analysis cache.
     * 
     * @param string $key
     * @return bool
     */
    public function invalidate(string $key): bool
    {
        return Cache::forget('sipasar_analysis_' . $key);
    }
}
