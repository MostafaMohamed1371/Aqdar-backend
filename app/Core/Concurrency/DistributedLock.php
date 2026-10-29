<?php

namespace App\Core\Concurrency;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Distributed locks to prevent race conditions across stateless app servers.
 */
class DistributedLock
{
    public function __construct(
        protected string $prefix = '',
        protected int $seconds = 0,
    ) {
        $this->prefix = $prefix !== '' ? $prefix : (string) config('structer.scaling.lock_prefix', 'lock:');
        $this->seconds = $seconds > 0 ? $seconds : (int) config('structer.scaling.lock_seconds', 10);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws LockTimeoutException
     */
    public function block(string $name, Closure $callback, int $waitSeconds = 5): mixed
    {
        return Cache::lock($this->prefix.$name, $this->seconds)->block($waitSeconds, $callback);
    }
}
