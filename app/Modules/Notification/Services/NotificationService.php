<?php

namespace App\Modules\Notification\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Broadcasting\SafeBroadcast;
use App\Core\Enums\DeliveryStatus;
use App\Core\Enums\NotificationCategory;
use App\Core\Enums\NotificationChannel;
use App\Core\Enums\Permission;
use App\Core\Enums\StaffRole;
use App\Modules\Crm\Models\Lead;
use App\Modules\Listing\Services\ContactLeakScanner;
use App\Modules\Notification\Events\StaffInboxReceived;
use App\Modules\Notification\Jobs\SendBroadcastJob;
use App\Modules\Notification\Jobs\SendPushJob;
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\Notification\Models\NotificationBroadcast;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\Notification\Services\Push\PushGateway;
use App\Modules\Setting\Services\SettingService;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserDevice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NotificationService
{
    /** @var list<string> */
    private const SAFE_KEYS = [
        'listing_code', 'listing_id', 'area', 'unit_type', 'client_label',
        'public_code', 'lead_code', 'event', 'price', 'status',
    ];

    public function __construct(
        private PushGateway $push,
        private SettingService $settings,
        private ContactLeakScanner $scanner,
        private AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $context */
    public function notifySafely(Model $notifiable, string $eventKey, array $context = [], ?string $idempotency = null, ?array $copy = null): ?InboxNotification
    {
        try {
            return $this->notify($notifiable, $eventKey, $context, $idempotency, $copy);
        } catch (\Throwable $e) {
            Log::warning('aqdar.notification.failed', [
                'event' => $eventKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array{title_ar?: string, title_en?: string, body_ar?: string, body_en?: string}|null  $copy
     */
    public function notify(Model $notifiable, string $eventKey, array $context = [], ?string $idempotency = null, ?array $copy = null): InboxNotification
    {
        $safe = $this->safeContext($context);
        $safe['event'] = $eventKey;
        $category = $this->categoryFor($eventKey);
        if ($category === NotificationCategory::Smart && ! $this->smartEnabled()) {
            throw ValidationException::withMessages([
                'category' => [__('aqdar.notifications.smart_disabled')],
            ]);
        }
        if ($category === NotificationCategory::Marketing && $notifiable instanceof User && ! $this->preferences($notifiable)->marketing_opt_in) {
            throw ValidationException::withMessages([
                'audience' => [__('aqdar.notifications.marketing_opt_in_required')],
            ]);
        }

        $key = $idempotency ?? $this->idempotencyKey($eventKey, $notifiable, $safe);
        $existing = InboxNotification::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            return $existing;
        }

        $rendered = $this->render($eventKey, $safe, $copy);
        $notification = InboxNotification::query()->create([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'event_key' => $eventKey,
            'template_key' => $eventKey,
            'category' => $category,
            'idempotency_key' => $key,
            'title_ar' => $rendered['title_ar'],
            'title_en' => $rendered['title_en'],
            'body_ar' => $rendered['body_ar'],
            'body_en' => $rendered['body_en'],
            'data' => $safe,
        ]);

        $this->recordDelivery($notification, NotificationChannel::InApp, DeliveryStatus::Sent, [
            'sent_at' => now(),
            'provider_response' => 'in-app',
        ]);
        $this->queuePush($notification, $notifiable);

        if ($notifiable instanceof StaffUser) {
            SafeBroadcast::dispatch(new StaffInboxReceived(
                $notifiable,
                $notification,
                $this->unreadCount($notifiable),
            ));
        }

        return $notification;
    }

    public function forUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = InboxNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->latest('id');

        $category = (string) ($filters['category'] ?? '');
        if ($category === NotificationCategory::Smart->value && ! $this->smartEnabled()) {
            $query->whereKey(-1);
        } elseif ($category !== '' && $category !== 'all') {
            $query->where('category', $category);
        }
        if (! empty($filters['unread'])) {
            $query->whereNull('read_at');
        }

        return $query->paginate(20)->withQueryString();
    }

    public function unreadCount(Model $notifiable): int
    {
        return InboxNotification::query()
            ->where('notifiable_type', $notifiable->getMorphClass())
            ->where('notifiable_id', $notifiable->getKey())
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(User $user, InboxNotification $notification): InboxNotification
    {
        abort_unless(
            $notification->notifiable_type === $user->getMorphClass()
            && (int) $notification->notifiable_id === (int) $user->id,
            404,
        );
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification->fresh() ?? $notification;
    }

    public function markAllRead(User $user): int
    {
        return InboxNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function preferences(User $user): NotificationPreference
    {
        return NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'push_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => true,
                'marketing_opt_in' => false,
            ],
        );
    }

    public function updatePreferences(User $user, array $data): NotificationPreference
    {
        $row = $this->preferences($user);
        $row->forceFill([
            'push_enabled' => array_key_exists('push_enabled', $data) ? (bool) $data['push_enabled'] : $row->push_enabled,
            'email_enabled' => array_key_exists('email_enabled', $data) ? (bool) $data['email_enabled'] : $row->email_enabled,
            'sms_enabled' => array_key_exists('sms_enabled', $data) ? (bool) $data['sms_enabled'] : $row->sms_enabled,
            'marketing_opt_in' => array_key_exists('marketing_opt_in', $data) ? (bool) $data['marketing_opt_in'] : $row->marketing_opt_in,
        ])->save();

        return $row->fresh() ?? $row;
    }

    public function updateTemplate(NotificationTemplate $template, array $data, StaffUser $staff): NotificationTemplate
    {
        $this->assertSafeCopy($data['title_ar'] ?? $template->title_ar, $data['title_en'] ?? $template->title_en, $data['body_ar'] ?? $template->body_ar, $data['body_en'] ?? $template->body_en);
        $template->forceFill([
            'title_ar' => $data['title_ar'] ?? $template->title_ar,
            'title_en' => $data['title_en'] ?? $template->title_en,
            'body_ar' => $data['body_ar'] ?? $template->body_ar,
            'body_en' => $data['body_en'] ?? $template->body_en,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $template->is_active,
        ])->save();
        $this->audit->write('notification.template_updated', $staff, $template, ['key' => $template->key]);

        return $template->fresh() ?? $template;
    }

    /** @param array<string, mixed> $data */
    public function testSendToStaff(StaffUser $actor, array $data): int
    {
        $this->assertSafeCopy($data['title_ar'], $data['title_en'] ?? $data['title_ar'], $data['body_ar'], $data['body_en'] ?? $data['body_ar']);
        $count = 0;
        foreach (StaffUser::query()->where('is_active', true)->get() as $staff) {
            $this->notifySafely($staff, 'staff_test', [
                'event' => 'staff_test',
            ], 'staff_test:'.$actor->id.':'.$staff->id.':'.now()->timestamp, [
                'title_ar' => $data['title_ar'],
                'title_en' => $data['title_en'] ?? $data['title_ar'],
                'body_ar' => $data['body_ar'],
                'body_en' => $data['body_en'] ?? $data['body_ar'],
            ]);
            $count++;
        }
        $this->audit->write('notification.staff_test', $actor, null, ['count' => $count]);

        return $count;
    }

    /**
     * Send one live FCM payload to the newest marketplace device that has a push token.
     *
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, response: string, user_id: int|null}
     */
    public function diagnosticDevicePush(StaffUser $actor, array $data): array
    {
        $this->assertSafeCopy($data['title_ar'], $data['title_en'] ?? $data['title_ar'], $data['body_ar'], $data['body_en'] ?? $data['body_ar']);

        $device = UserDevice::query()
            ->whereNotNull('push_token')
            ->where('push_token', '!=', '')
            ->latest('last_seen_at')
            ->latest('id')
            ->first();

        if ($device === null) {
            throw ValidationException::withMessages([
                'push_token' => [__('aqdar.notifications.fcm_no_device')],
            ]);
        }

        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $title = $locale === 'en'
            ? (string) ($data['title_en'] ?? $data['title_ar'])
            : (string) $data['title_ar'];
        $body = $locale === 'en'
            ? (string) ($data['body_en'] ?? $data['body_ar'])
            : (string) $data['body_ar'];

        $result = $this->push->send((string) $device->push_token, [
            'title' => $title,
            'body' => $body,
            'data' => [
                'event' => 'fcm_diagnostic',
                'deep_link' => '/notifications',
            ],
        ]);

        if ($result->tokenExpired) {
            $device->forceFill(['push_token' => null])->save();
        }

        $this->audit->write('notification.fcm_diagnostic', $actor, $device->user, [
            'ok' => $result->ok,
            'response' => $result->response,
            'device_id' => $device->id,
        ]);

        return [
            'ok' => $result->ok,
            'response' => (string) $result->response,
            'user_id' => $device->user_id,
        ];
    }

    /** @param array<string, mixed> $data */
    public function scheduleBroadcast(StaffUser $actor, array $data): NotificationBroadcast
    {
        $this->assertSafeCopy($data['title_ar'], $data['title_en'] ?? $data['title_ar'], $data['body_ar'], $data['body_en'] ?? $data['body_ar']);
        $when = ! empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : now();
        $autoApprove = $actor->isSuperAdmin();
        $broadcast = NotificationBroadcast::query()->create([
            'title_ar' => $data['title_ar'],
            'title_en' => $data['title_en'] ?? $data['title_ar'],
            'body_ar' => $data['body_ar'],
            'body_en' => $data['body_en'] ?? $data['body_ar'],
            'audience' => $data['audience'] ?? 'marketing_opted_in',
            'status' => $autoApprove ? ($when->isFuture() ? 'scheduled' : 'queued') : 'pending',
            'scheduled_at' => $when,
            'created_by' => $actor->id,
            'approved_at' => $autoApprove ? now() : null,
            'approved_by' => $autoApprove ? $actor->id : null,
        ]);
        $this->audit->write('notification.broadcast_scheduled', $actor, $broadcast, [
            'scheduled_at' => $when->toIso8601String(),
            'approved' => $autoApprove,
        ]);
        if ($autoApprove && ! $when->isFuture()) {
            SendBroadcastJob::dispatch($broadcast->id)->afterCommit();
        }

        return $broadcast;
    }

    public function approveBroadcast(NotificationBroadcast $broadcast, StaffUser $actor): NotificationBroadcast
    {
        if ($broadcast->isApproved()) {
            return $broadcast;
        }

        if ((int) $broadcast->created_by === (int) $actor->id && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'broadcast' => [__('aqdar.notifications.cannot_approve_own')],
            ]);
        }

        $when = $broadcast->scheduled_at ?? now();
        $broadcast->forceFill([
            'approved_at' => now(),
            'approved_by' => $actor->id,
            'status' => $when->isFuture() ? 'scheduled' : 'queued',
        ])->save();
        $this->audit->write('notification.broadcast_approved', $actor, $broadcast);

        if (! $when->isFuture()) {
            SendBroadcastJob::dispatch($broadcast->id)->afterCommit();
        }

        return $broadcast->fresh() ?? $broadcast;
    }

    public function dispatchDueBroadcasts(): int
    {
        $rows = NotificationBroadcast::query()
            ->whereNotNull('approved_at')
            ->whereIn('status', ['scheduled', 'queued'])
            ->where('scheduled_at', '<=', now())
            ->get();
        foreach ($rows as $row) {
            SendBroadcastJob::dispatch($row->id);
        }

        return $rows->count();
    }

    public function dispatchBroadcast(int $broadcastId): void
    {
        $broadcast = NotificationBroadcast::query()->find($broadcastId);
        if ($broadcast === null || $broadcast->sent_at !== null || ! $broadcast->isApproved()) {
            return;
        }
        $users = User::query()
            ->whereNull('closed_at')
            ->whereNull('close_requested_at')
            ->whereNull('suspended_at')
            ->whereHas('notificationPreference', fn ($query) => $query->where('marketing_opt_in', true))
            ->get();
        foreach ($users as $user) {
            $this->notifySafely($user, 'marketing_broadcast', [
                'event' => 'marketing_broadcast',
            ], 'broadcast:'.$broadcast->id.':user:'.$user->id, [
                'title_ar' => $broadcast->title_ar,
                'title_en' => $broadcast->title_en,
                'body_ar' => $broadcast->body_ar,
                'body_en' => $broadcast->body_en,
            ]);
        }
        $broadcast->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
    }

    public function notifyOverdueFollowups(): int
    {
        $leads = Lead::query()
            ->overdueFollowUp()
            ->with(['assignee', 'listing'])
            ->get();
        $count = 0;
        foreach ($leads as $lead) {
            $activity = $lead->activities()->overdueFollowUp()->first();
            $key = 'sales_followup_overdue:lead:'.$lead->id.($activity !== null ? ':activity:'.$activity->id : '');
            $context = [
                'lead_code' => $lead->public_code,
                'listing_code' => $lead->listing?->public_code,
            ];
            $staff = $lead->assignee;
            if ($staff !== null && $this->notifySafely($staff, 'sales_followup_overdue', $context, $key) !== null) {
                $count++;
            }
            $count += $this->notifyStaffQueue('sales_followup_overdue', $context, Permission::ManageCrm, true, $key.':manager');
        }

        return $count;
    }

    public function forStaff(StaffUser $staff): LengthAwarePaginator
    {
        return InboxNotification::query()
            ->where('notifiable_type', $staff->getMorphClass())
            ->where('notifiable_id', $staff->id)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
    }

    public function markReadFor(Model $notifiable, InboxNotification $notification): InboxNotification
    {
        abort_unless(
            $notification->notifiable_type === $notifiable->getMorphClass()
            && (int) $notification->notifiable_id === (int) $notifiable->getKey(),
            404,
        );
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification->fresh() ?? $notification;
    }

    /**
     * In-app staff queue alerts. Push is skipped for StaffUser (dashboard is the staff channel).
     *
     * @param  array<string, mixed>  $context
     */
    public function notifyStaffQueue(string $eventKey, array $context, Permission $permission, bool $excludeSales = false, ?string $idempotency = null): int
    {
        $count = 0;
        $staff = StaffUser::query()->where('is_active', true)->with('role.permissions')->get();
        foreach ($staff as $member) {
            if (! $member->hasPermission($permission)) {
                continue;
            }
            if ($excludeSales && $member->role?->slug === StaffRole::Sales->value) {
                continue;
            }
            $memberKey = $idempotency === null
                ? null
                : $idempotency.':'.$member->getMorphClass().':'.$member->getKey();
            $this->notifySafely($member, $eventKey, $context, $memberKey);
            $count++;
        }

        return $count;
    }

    public function attemptPush(int $deliveryId): void
    {
        $delivery = NotificationDelivery::query()->with(['notification', 'device'])->find($deliveryId);
        if ($delivery === null || $delivery->channel !== NotificationChannel::Push) {
            return;
        }
        if ($delivery->status === DeliveryStatus::Sent) {
            return;
        }
        $delivery->increment('attempts');
        $token = (string) ($delivery->device?->push_token ?? '');
        $notification = $delivery->notification;
        $payload = [
            'title' => $notification?->localizedTitle(),
            'body' => $notification?->localizedBody(),
            'data' => array_filter([
                ...$this->safeContext((array) ($notification?->data ?? [])),
                'notification_id' => $notification?->id,
                'event' => $notification?->event_key,
            ], fn ($value) => $value !== null && $value !== ''),
        ];
        try {
            $result = $this->push->send($token, $payload);
        } catch (\Throwable $e) {
            $delivery->forceFill([
                'status' => DeliveryStatus::Failed,
                'provider_response' => $e->getMessage(),
            ])->save();

            return;
        }
        if ($result->tokenExpired && $delivery->device !== null) {
            $delivery->device->forceFill(['push_token' => null])->save();
            $delivery->forceFill([
                'status' => DeliveryStatus::Failed,
                'provider_response' => $result->response,
                'skip_reason' => 'token_expired',
            ])->save();

            return;
        }
        $delivery->forceFill([
            'status' => $result->ok ? DeliveryStatus::Sent : DeliveryStatus::Failed,
            'provider_response' => $result->response,
            'sent_at' => $result->ok ? now() : null,
        ])->save();
    }

    public function smartEnabled(): bool
    {
        $setting = $this->settings->findByKey('feature_smart_notifications');

        return (bool) ($setting?->value ?? config('aqdar.notifications.smart_enabled', false));
    }

    public function deliveries(): LengthAwarePaginator
    {
        return NotificationDelivery::query()
            ->with(['notification'])
            ->latest('id')
            ->paginate(30)
            ->withQueryString();
    }

    public function templates()
    {
        return NotificationTemplate::query()->orderBy('key')->get();
    }

    public function broadcasts()
    {
        return NotificationBroadcast::query()->latest('id')->limit(20)->get();
    }

    private function queuePush(InboxNotification $notification, Model $notifiable): void
    {
        if (! $notifiable instanceof User) {
            $this->recordDelivery($notification, NotificationChannel::Push, DeliveryStatus::Skipped, [
                'skip_reason' => 'staff_in_app_only',
            ]);

            return;
        }
        if ($notifiable->suspended_at !== null && ! in_array($notification->event_key, ['account_suspended', 'account_reactivated'], true)) {
            $this->recordDelivery($notification, NotificationChannel::Push, DeliveryStatus::Skipped, [
                'skip_reason' => 'account_suspended',
            ]);

            return;
        }
        $prefs = $this->preferences($notifiable);
        if (! $prefs->push_enabled) {
            $this->recordDelivery($notification, NotificationChannel::Push, DeliveryStatus::Skipped, [
                'skip_reason' => 'push_disabled',
            ]);

            return;
        }
        if ($this->inQuietHours()) {
            $this->recordDelivery($notification, NotificationChannel::Push, DeliveryStatus::Skipped, [
                'skip_reason' => 'quiet_hours',
            ]);

            return;
        }
        $devices = $notifiable->devices()
            ->whereNotNull('push_token')
            ->where('push_token', '!=', '')
            ->get()
            ->unique(fn (UserDevice $device): string => (string) $device->push_token);
        if ($devices->isEmpty()) {
            $this->recordDelivery($notification, NotificationChannel::Push, DeliveryStatus::Skipped, [
                'skip_reason' => 'no_device',
            ]);

            return;
        }
        foreach ($devices as $device) {
            $row = $this->recordDelivery($notification, NotificationChannel::Push, DeliveryStatus::Pending, [
                'device_id' => $device->id,
            ]);
            SendPushJob::dispatch($row->id)->afterCommit();
        }
    }

    /** @param array<string, mixed> $attrs */
    private function recordDelivery(InboxNotification $notification, NotificationChannel $channel, DeliveryStatus $status, array $attrs = []): NotificationDelivery
    {
        $match = [
            'notification_id' => $notification->id,
            'channel' => $channel->value,
            'device_id' => $attrs['device_id'] ?? null,
        ];
        $existing = NotificationDelivery::query()->where($match)->first();
        if ($existing !== null) {
            return $existing;
        }

        return NotificationDelivery::query()->create($match + [
            'status' => $status->value,
            'attempts' => 0,
            'provider_response' => $attrs['provider_response'] ?? null,
            'skip_reason' => $attrs['skip_reason'] ?? null,
            'sent_at' => $attrs['sent_at'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array{title_ar?: string, title_en?: string, body_ar?: string, body_en?: string}|null  $copy
     */
    private function render(string $eventKey, array $context, ?array $copy = null): array
    {
        $template = NotificationTemplate::query()->where('key', $eventKey)->where('is_active', true)->first();
        $replace = [];
        foreach ($context as $key => $value) {
            $replace[':'.$key] = (string) $value;
        }
        $apply = fn (string $text): string => strtr($text, $replace);

        return [
            'title_ar' => $apply($copy['title_ar'] ?? $template?->title_ar ?? __('aqdar.notifications.events.'.$eventKey.'.title', locale: 'ar')),
            'title_en' => $apply($copy['title_en'] ?? $template?->title_en ?? __('aqdar.notifications.events.'.$eventKey.'.title', locale: 'en')),
            'body_ar' => $apply($copy['body_ar'] ?? $template?->body_ar ?? __('aqdar.notifications.events.'.$eventKey.'.body', locale: 'ar')),
            'body_en' => $apply($copy['body_en'] ?? $template?->body_en ?? __('aqdar.notifications.events.'.$eventKey.'.body', locale: 'en')),
        ];
    }

    /** @param array<string, mixed> $context */
    private function safeContext(array $context): array
    {
        $out = [];
        foreach (self::SAFE_KEYS as $key) {
            if (! array_key_exists($key, $context) || $context[$key] === null || $context[$key] === '') {
                continue;
            }
            $value = $context[$key];
            if (is_scalar($value)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function categoryFor(string $eventKey): NotificationCategory
    {
        return match (true) {
            str_starts_with($eventKey, 'listing_') => NotificationCategory::Listing,
            str_starts_with($eventKey, 'inquiry_')
                || str_starts_with($eventKey, 'seller_')
                || str_starts_with($eventKey, 'support_')
                || str_starts_with($eventKey, 'appointment_') => NotificationCategory::Inquiry,
            str_starts_with($eventKey, 'buyer_request_')
                || str_starts_with($eventKey, 'case_') => NotificationCategory::Case,
            $eventKey === 'marketing_broadcast' => NotificationCategory::Marketing,
            str_starts_with($eventKey, 'smart_') => NotificationCategory::Smart,
            default => NotificationCategory::System,
        };
    }

    private function idempotencyKey(string $eventKey, Model $notifiable, array $context): string
    {
        $parts = [
            $eventKey,
            $notifiable->getMorphClass(),
            (string) $notifiable->getKey(),
            (string) ($context['public_code'] ?? $context['listing_code'] ?? $context['listing_id'] ?? $context['lead_code'] ?? ''),
            (string) ($context['client_label'] ?? ''),
            (string) ($context['status'] ?? ''),
        ];

        return implode(':', array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    private function inQuietHours(): bool
    {
        $setting = $this->settings->findByKey('notification_quiet_hours');
        $window = (string) ($setting?->value ?? config('aqdar.notifications.quiet_hours', ''));
        if ($window === '' || ! str_contains($window, '-')) {
            return false;
        }
        [$start, $end] = array_map('trim', explode('-', $window, 2));
        try {
            $now = now();
            $from = Carbon::createFromFormat('H:i', $start)?->setDateFrom($now);
            $to = Carbon::createFromFormat('H:i', $end)?->setDateFrom($now);
        } catch (\Throwable) {
            return false;
        }
        if ($from === null || $to === null) {
            return false;
        }
        if ($from->equalTo($to)) {
            return false;
        }
        if ($from->lessThan($to)) {
            return $now->betweenIncluded($from, $to);
        }

        return $now->greaterThanOrEqualTo($from) || $now->lessThanOrEqualTo($to);
    }

    private function assertSafeCopy(string ...$texts): void
    {
        $leak = $this->scanner->scan(...$texts);
        if ($leak !== null) {
            throw ValidationException::withMessages([
                'body_ar' => [__('aqdar.notifications.no_third_party_pii')],
            ]);
        }
    }
}
