<?php

namespace App\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class ScalingCheckCommand extends Command
{
    protected $signature = 'structer:scaling-check';

    protected $description = 'Verify Redis, queue, cache, and database scaling configuration';

    public function handle(): int
    {
        $this->components->info('Structer scaling readiness check');
        $ok = true;

        $ok = $this->checkRedis() && $ok;
        $ok = $this->checkCacheDriver() && $ok;
        $ok = $this->checkSessionDriver() && $ok;
        $ok = $this->checkQueueDriver() && $ok;
        $ok = $this->checkDatabaseReadWrite() && $ok;

        if ($ok) {
            $this->components->info('All scaling checks passed.');

            return self::SUCCESS;
        }

        $this->components->error('Some scaling checks failed. See SCALING.md for setup steps.');

        return self::FAILURE;
    }

    protected function checkRedis(): bool
    {
        try {
            Redis::connection()->ping();

            $this->components->twoColumnDetail('Redis', '<fg=green>OK</>');

            return true;
        } catch (\Throwable $e) {
            $this->components->twoColumnDetail('Redis', '<fg=red>FAIL</> — '.$e->getMessage());

            return false;
        }
    }

    protected function checkCacheDriver(): bool
    {
        $driver = config('cache.default');
        $pass = $driver === 'redis';

        try {
            Cache::store()->put('structer:scaling:probe', '1', 5);
            Cache::store()->forget('structer:scaling:probe');
        } catch (\Throwable $e) {
            $pass = false;
            $this->components->twoColumnDetail('Cache', '<fg=red>FAIL</> — '.$e->getMessage());

            return false;
        }

        $label = $pass ? '<fg=green>OK</>' : '<fg=yellow>WARN</> (recommended: redis, current: '.$driver.')';
        $this->components->twoColumnDetail('Cache driver', $label);

        return $pass;
    }

    protected function checkSessionDriver(): bool
    {
        $driver = config('session.driver');
        $pass = $driver === 'redis';
        $label = $pass ? '<fg=green>OK</>' : '<fg=yellow>WARN</> (recommended: redis, current: '.$driver.')';
        $this->components->twoColumnDetail('Session driver', $label);

        return $pass;
    }

    protected function checkQueueDriver(): bool
    {
        $driver = config('queue.default');
        $pass = $driver === 'redis';
        $label = $pass ? '<fg=green>OK</>' : '<fg=yellow>WARN</> (recommended: redis, current: '.$driver.')';
        $this->components->twoColumnDetail('Queue driver', $label);

        if ($pass) {
            $this->components->twoColumnDetail('Horizon queues', 'payments → default → emails');
        }

        try {
            Queue::connection()->size(config('structer.queues.payments', 'payments'));
        } catch (\Throwable) {
            // Non-fatal when redis queue is configured but workers are offline.
        }

        return $pass;
    }

    protected function checkDatabaseReadWrite(): bool
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}", []);

        $driver = $config['driver'] ?? '';

        if (! in_array($driver, ['mysql', 'pgsql'], true) || ! isset($config['read'], $config['write'])) {
            $this->components->twoColumnDetail('DB read/write split', '<fg=yellow>SKIP</> (needs mysql/pgsql read+write hosts)');

            return true;
        }

        $readHosts = $config['read']['host'] ?? [];
        $writeHosts = $config['write']['host'] ?? [];
        $split = count(array_unique($readHosts)) > 1 || count(array_unique($writeHosts)) > 1
            || ($readHosts[0] ?? null) !== ($writeHosts[0] ?? null);

        try {
            DB::connection()->select('select 1 as ok');
        } catch (\Throwable $e) {
            $this->components->twoColumnDetail('Database', '<fg=red>FAIL</> — '.$e->getMessage());

            return false;
        }

        $label = $split
            ? '<fg=green>OK</> (read/write hosts configured)'
            : '<fg=yellow>WARN</> (single host — add DB_READ_HOST / DB_WRITE_HOST for replicas)';

        $this->components->twoColumnDetail('DB read/write split', $label);

        return true;
    }
}
