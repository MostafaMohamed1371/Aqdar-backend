<?php

namespace App\Core\Audit;

use App\Core\Audit\Models\AccessLog;
use App\Core\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    /** @param array<string, mixed> $meta */
    public function write(
        string $action,
        ?Model $actor = null,
        ?Model $subject = null,
        array $meta = [],
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::query()->create([
            'action' => $action,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $meta */
    public function access(
        string $action,
        ?Model $actor = null,
        ?Model $subject = null,
        array $meta = [],
        ?Request $request = null,
    ): AccessLog {
        $request ??= request();

        return AccessLog::query()->create([
            'action' => $action,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
