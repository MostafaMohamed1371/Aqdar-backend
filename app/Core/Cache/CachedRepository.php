<?php

namespace App\Core\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-aside wrapper for rarely-changing data (settings, categories, etc.).
 */
class CachedRepository
{
    public function __construct(
        protected string $prefix,
        protected ?int $ttlSeconds = null,
    ) {}

    public function remember(string $key, Closure $callback): mixed
    {
        $cacheKey = $this->prefix.':'.$key;
        $ttl = $this->ttlSeconds ?? (int) config('structer.scaling.cache_ttl_seconds', 3600);

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    public function forget(string $key): bool
    {
        return Cache::forget($this->prefix.':'.$key);
    }

    /** @param  list<string>  $keys */
    public function forgetMany(array $keys): void
    {
        foreach ($keys as $key) {
            $this->forget($key);
        }
    }

    public function flushPrefix(): void
    {
        // Tag-free flush: callers should forget known keys or use cache tags when available.
    }
}
