<?php

namespace App\Modules\Crm\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\PublicCodePrefix;
use App\Core\Enums\StaffRole;
use App\Core\Enums\SupportAuthorType;
use App\Core\Enums\SupportThreadStatus;
use App\Core\Identity\PublicCodeGenerator;
use App\Modules\Crm\Models\SupportMessage;
use App\Modules\Crm\Models\SupportThread;
use App\Modules\Listing\Services\ContactLeakScanner;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Setting\Services\SettingService;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportService
{
    public function __construct(
        private PublicCodeGenerator $codes,
        private ContactLeakScanner $scanner,
        private SettingService $settings,
        private AuditLogger $audit,
        private NotificationService $notifications,
        private LeadService $leads,
    ) {}

    public function currentForUser(User $user): ?SupportThread
    {
        return SupportThread::query()
            ->where('user_id', $user->id)
            ->where('status', SupportThreadStatus::Open)
            ->latest('id')
            ->first();
    }

    public function showForUser(User $user, SupportThread $thread): SupportThread
    {
        abort_unless((int) $thread->user_id === (int) $user->id, 404);

        return $thread->load('messages');
    }

    /** @param array<string, mixed> $data */
    public function postFromUser(User $user, array $data): SupportThread
    {
        if (! ($data['consent'] ?? false)) {
            throw ValidationException::withMessages([
                'consent' => [__('aqdar.crm.consent_required')],
            ]);
        }
        $this->assertClean((string) $data['body']);

        $thread = DB::transaction(function () use ($user, $data): SupportThread {
            $thread = $this->currentForUser($user);
            if ($thread === null) {
                $thread = SupportThread::query()->create([
                    'public_code' => $this->codes->next(PublicCodePrefix::Support),
                    'user_id' => $user->id,
                    'status' => SupportThreadStatus::Open,
                    'consent_at' => now(),
                ]);
                $this->autoAssign($thread);
            }

            SupportMessage::query()->create([
                'thread_id' => $thread->id,
                'author_type' => SupportAuthorType::User,
                'user_id' => $user->id,
                'body' => $data['body'],
                'created_at' => now(),
            ]);

            return $thread;
        });

        $this->audit->write('support.message_from_user', $user, $thread, [
            'public_code' => $thread->public_code,
        ]);

        return $thread->load('messages');
    }

    /** @param array<string, mixed> $filters */
    public function deskIndex(StaffUser $staff, array $filters): LengthAwarePaginator
    {
        $query = SupportThread::query()->with(['user', 'assignee'])->latest('id');
        $this->restrict($query, $staff);
        if (($filters['status'] ?? 'all') !== 'all' && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(20)->withQueryString();
    }

    public function deskShow(StaffUser $staff, SupportThread $thread): SupportThread
    {
        $this->assertCanAct($staff, $thread);

        return $thread->load(['user', 'assignee', 'messages.staff']);
    }

    public function reply(StaffUser $staff, SupportThread $thread, string $body): SupportThread
    {
        $this->assertCanAct($staff, $thread);
        abort_if($thread->status !== SupportThreadStatus::Open, 422, __('aqdar.support.closed'));
        $this->assertClean($body);

        $message = SupportMessage::query()->create([
            'thread_id' => $thread->id,
            'author_type' => SupportAuthorType::Staff,
            'staff_id' => $staff->id,
            'body' => $body,
            'created_at' => now(),
        ]);

        $this->audit->write('support.replied', $staff, $thread, [
            'public_code' => $thread->public_code,
        ]);

        if ($thread->user !== null) {
            $this->notifications->notifySafely($thread->user, 'support_reply', [
                'public_code' => $thread->public_code,
            ], 'support_reply:'.$message->id);
        }

        return $thread->load('messages');
    }

    public function close(StaffUser $staff, SupportThread $thread): SupportThread
    {
        $this->assertCanAct($staff, $thread);
        $thread->forceFill(['status' => SupportThreadStatus::Closed])->save();
        $this->audit->write('support.closed', $staff, $thread, [
            'public_code' => $thread->public_code,
        ]);

        return $thread;
    }

    public function assertCanAct(StaffUser $staff, SupportThread $thread): void
    {
        $staff->loadMissing('role');
        if ($this->leads->canManageAll($staff)) {
            return;
        }
        if ($staff->role?->slug === StaffRole::Sales->value && (int) $thread->assigned_to === (int) $staff->id) {
            return;
        }
        abort(403, __('aqdar.http.forbidden'));
    }

    private function autoAssign(SupportThread $thread): void
    {
        if ($thread->assigned_to !== null) {
            return;
        }
        $rule = $this->settings->stringValue(
            'lead_assignment_rule',
            (string) config('aqdar.crm.assignment_rule', 'manual'),
        );
        if ($rule !== 'round_robin') {
            return;
        }
        $sales = StaffUser::query()
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $query) => $query->where('slug', StaffRole::Sales->value))
            ->orderBy('id')
            ->get();
        if ($sales->isEmpty()) {
            return;
        }
        $cursor = (int) $this->settings->stringValue('lead_assignment_cursor', '0');
        $next = $sales->first(fn (StaffUser $member) => (int) $member->id > $cursor) ?? $sales->first();
        $thread->forceFill(['assigned_to' => $next->id])->save();
        $this->settings->put('lead_assignment_cursor', (string) $next->id, 'crm');
    }

    private function restrict(Builder $query, StaffUser $staff): void
    {
        if ($this->leads->canManageAll($staff)) {
            return;
        }
        $query->where('assigned_to', $staff->id);
    }

    private function assertClean(string $body): void
    {
        if ($this->scanner->scan($body) !== null) {
            throw ValidationException::withMessages([
                'body' => [__('aqdar.crm.message_no_contact')],
            ]);
        }
    }
}
