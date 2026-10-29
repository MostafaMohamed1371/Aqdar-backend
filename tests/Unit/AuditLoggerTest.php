<?php

namespace Tests\Unit;

use App\Core\Audit\AuditLogger;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_writes_audit_and_access_rows(): void
    {
        $user = User::factory()->create();
        $logger = app(AuditLogger::class);

        $audit = $logger->write('user.suspended', $user, $user, ['reason' => 'abuse']);
        $access = $logger->access('user.phone_revealed', $user, $user);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $audit->id,
            'action' => 'user.suspended',
        ]);
        $this->assertDatabaseHas('access_logs', [
            'id' => $access->id,
            'action' => 'user.phone_revealed',
        ]);
    }
}
