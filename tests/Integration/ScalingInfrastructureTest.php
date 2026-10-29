<?php

namespace Tests\Integration;

use App\Core\Cache\CachedRepository;
use App\Core\Jobs\SendEmailJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ScalingInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_cached_repository_remembers_values(): void
    {
        Cache::flush();

        $repo = new CachedRepository('test-settings', 60);
        $calls = 0;

        $first = $repo->remember('app', function () use (&$calls) {
            $calls++;

            return ['locale' => 'en'];
        });

        $second = $repo->remember('app', function () use (&$calls) {
            $calls++;

            return ['locale' => 'ar'];
        });

        $this->assertSame(['locale' => 'en'], $first);
        $this->assertSame(['locale' => 'en'], $second);
        $this->assertSame(1, $calls);
    }

    public function test_email_job_uses_emails_queue(): void
    {
        $job = new SendEmailJob('user@example.com', 'Hello', 'Body');

        $this->assertSame('emails', $job->queue);
    }

}
