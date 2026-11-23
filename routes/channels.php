<?php

use App\Core\Enums\Permission;
use App\Modules\Crm\Models\SupportThread;
use App\Modules\Crm\Services\SupportService;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('desk', function ($user): bool {
    return $user instanceof StaffUser && $user->is_active;
});

Broadcast::channel('staff.{staffId}', function ($user, int $staffId): bool {
    return $user instanceof StaffUser
        && (int) $user->id === $staffId
        && $user->is_active;
});

Broadcast::channel('support.desk', function ($user): bool {
    return $user instanceof StaffUser
        && $user->is_active
        && $user->hasPermission(Permission::ManageCrm);
});

Broadcast::channel('support.thread.{threadId}', function ($user, int $threadId): bool {
    $thread = SupportThread::query()->find($threadId);
    if ($thread === null || $user === null) {
        return false;
    }

    if ($user instanceof User) {
        return (int) $thread->user_id === (int) $user->id;
    }

    if ($user instanceof StaffUser) {
        return app(SupportService::class)->canAct($user, $thread);
    }

    return false;
});
