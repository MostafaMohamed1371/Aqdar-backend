<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Audit\Models\AuditLog;
use App\Core\Enums\DeliveryStatus;
use App\Core\Enums\NotificationChannel;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Dashboard\Http\Requests\UpdatePlatformSettingsRequest;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Services\Push\FcmConfiguration;
use App\Modules\Setting\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingController extends BaseController
{
    /** @var array<string, string> */
    private const GROUPS = [
        'platform_name' => 'platform',
        'support_email' => 'platform',
        'sales_name' => 'sales',
        'sales_name_en' => 'sales',
        'sales_phone' => 'sales',
        'sales_hours' => 'sales',
        'sales_hours_en' => 'sales',
        'lead_assignment_rule' => 'crm',
        'maintenance_mode' => 'platform',
        'feature_smart_notifications' => 'notifications',
        'commission_default_rate' => 'commission',
        'audit_retention_months' => 'platform',
        'notification_quiet_hours' => 'notifications',
        'default_locale' => 'platform',
    ];

    public function __construct(
        private SettingService $settingService,
        private AuditLogger $audit,
        private FcmConfiguration $fcm,
    ) {}

    public function index(Request $request): View
    {
        return view('dashboard::settings.index', [
            'form' => $this->settingService->platformForm(),
            'tab' => $this->tab($request->query('tab')),
            'lastChange' => AuditLog::query()
                ->where('action', 'settings.updated')
                ->with('actor')
                ->latest('id')
                ->first(),
            'providerStatus' => $this->pushProviderStatus(),
            'providerStatuses' => $this->providerStatuses(),
            'diagnostics' => $this->diagnostics(),
        ]);
    }

    /** @return array{failed_jobs: int, last_failed_at: ?string, push_failed: int, push_pending: int} */
    private function diagnostics(): array
    {
        $since = now()->subDays(7);
        $pushDeliveries = NotificationDelivery::query()
            ->where('channel', NotificationChannel::Push->value)
            ->where('created_at', '>=', $since);

        return [
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'last_failed_at' => DB::table('failed_jobs')->max('failed_at'),
            'push_failed' => (clone $pushDeliveries)->where('status', DeliveryStatus::Failed->value)->count(),
            'push_pending' => (clone $pushDeliveries)->where('status', DeliveryStatus::Pending->value)->count(),
        ];
    }

    public function update(UpdatePlatformSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        foreach (self::GROUPS as $key => $group) {
            $this->settingService->put($key, (string) ($data[$key] ?? ''), $group);
        }

        $this->audit->write('settings.updated', $request->user(), null, [
            'keys' => array_keys(self::GROUPS),
        ]);

        $tab = $this->tab($request->input('tab'));

        return redirect()
            ->route('dashboard.settings.index', $tab === 'general' ? [] : ['tab' => $tab])
            ->with('status', __('aqdar.settings.saved'));
    }

    private function tab(mixed $tab): string
    {
        $value = (string) $tab;

        return in_array($value, ['general', 'security', 'audit'], true) ? $value : 'general';
    }

    /** @return array{sms: string, push: string, storage: string} */
    private function providerStatuses(): array
    {
        return [
            'sms' => $this->smsProviderStatus(),
            'push' => $this->pushProviderStatus(),
            'storage' => $this->storageProviderStatus(),
        ];
    }

    private function smsProviderStatus(): string
    {
        $driver = (string) config('aqdar.sms.driver', 'log');

        return match ($driver) {
            'http' => (string) config('aqdar.sms.http.url', '') !== ''
                ? __('aqdar.settings.provider_ok')
                : __('aqdar.settings.provider_sms_missing'),
            'null' => __('aqdar.settings.provider_null'),
            default => __('aqdar.settings.provider_log'),
        };
    }

    private function storageProviderStatus(): string
    {
        $path = storage_path('app/private/documents');

        return is_dir($path) && is_writable($path)
            ? __('aqdar.settings.provider_ok')
            : __('aqdar.settings.provider_storage_missing');
    }

    private function pushProviderStatus(): string
    {
        return $this->fcm->status()['label'];
    }
}
