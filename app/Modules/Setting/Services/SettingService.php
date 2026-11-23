<?php

namespace App\Modules\Setting\Services;

use App\Core\Cache\CachedRepository;
use App\Core\Services\AbstractService;
use App\Modules\Setting\Models\Setting;
use App\Modules\Setting\Repositories\SettingRepository;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class SettingService extends AbstractService
{
    public function __construct(
        SettingRepository $repository,
        private CachedRepository $cache,
    ) {
        parent::__construct($repository);
    }

    /**
     * @return array{
     *     currency: string,
     *     locale: string,
     *     default_locale: string,
     *     locales: list<string>,
     *     platform_name: string,
     *     maintenance: bool,
     *     sales_contact: array{name: string, phone: string, hours: string},
     *     consent: array{version: string, terms_url: string, privacy_url: string},
     *     otp: array{resend_after: int, length: int},
     *     pusher: array{enabled: bool, key: string, cluster: string, auth_endpoint: string, channel_prefix: string}
     * }
     */
    public function publicConfig(): array
    {
        $locales = config('aqdar.locales', ['ar', 'en']);
        $locales = is_array($locales) ? array_values($locales) : ['ar', 'en'];
        $defaultLocale = $this->stringValue('default_locale', (string) ($locales[0] ?? 'ar'));
        if (! in_array($defaultLocale, $locales, true)) {
            $defaultLocale = (string) ($locales[0] ?? 'ar');
        }

        return [
            'currency' => (string) config('aqdar.currency', 'EGP'),
            'locale' => app()->getLocale(),
            'default_locale' => $defaultLocale,
            'locales' => $locales,
            'platform_name' => $this->stringValue('platform_name', (string) config('aqdar.platform.name', 'AQDAR')),
            'maintenance' => $this->boolValue('maintenance_mode', false),
            'sales_contact' => [
                'name' => $this->localizedSalesValue('sales_name', 'sales_name_en', 'aqdar.sales.name', 'aqdar.sales.name_en'),
                'phone' => $this->stringValue('sales_phone', (string) config('aqdar.sales.phone')),
                'hours' => $this->localizedSalesValue('sales_hours', 'sales_hours_en', 'aqdar.sales.hours', 'aqdar.sales.hours_en'),
            ],
            'consent' => [
                'version' => (string) config('aqdar.consent.version', '1'),
                'terms_url' => (string) config('aqdar.consent.terms_url', ''),
                'privacy_url' => (string) config('aqdar.consent.privacy_url', ''),
            ],
            'otp' => [
                'resend_after' => max(1, (int) config('aqdar.otp.resend_after_seconds', 45)),
                'length' => max(4, (int) config('aqdar.otp.length', 4)),
            ],
            'pusher' => [
                'enabled' => (string) config('broadcasting.default') === 'pusher'
                    && (string) config('broadcasting.connections.pusher.key') !== '',
                'key' => (string) config('broadcasting.connections.pusher.key', ''),
                'cluster' => (string) config('broadcasting.connections.pusher.options.cluster', ''),
                'auth_endpoint' => '/api/v1/broadcasting/auth',
                'channel_prefix' => 'private-support.thread.',
            ],
        ];
    }

    /**
     * The Sales number buyers see. An active team that owns a number overrides the platform default for records
     * assigned to its members; everything else (including unassigned records) falls back to the platform number.
     *
     * @return array{name: string, phone: string, hours: string}
     */
    public function salesContactFor(?StaffUser $assignee): array
    {
        $contact = $this->publicConfig()['sales_contact'];
        $team = $assignee?->team;

        if ($team === null || ! $team->hasOwnSalesNumber()) {
            return $contact;
        }

        return [
            'name' => $team->localizedName(),
            'phone' => (string) $team->sales_phone,
            'hours' => $team->localizedHours() ?? $contact['hours'],
        ];
    }

    /** @return array<string, string> */
    public function platformForm(): array
    {
        return [
            'platform_name' => $this->stringValue('platform_name', (string) config('aqdar.platform.name', 'AQDAR')),
            'support_email' => $this->stringValue('support_email', (string) config('aqdar.platform.support_email', 'support@aqdar.eg')),
            'sales_name' => $this->stringValue('sales_name', (string) config('aqdar.sales.name')),
            'sales_name_en' => $this->stringValue('sales_name_en', (string) config('aqdar.sales.name_en')),
            'sales_phone' => $this->stringValue('sales_phone', (string) config('aqdar.sales.phone')),
            'sales_hours' => $this->stringValue('sales_hours', (string) config('aqdar.sales.hours')),
            'sales_hours_en' => $this->stringValue('sales_hours_en', (string) config('aqdar.sales.hours_en')),
            'lead_assignment_rule' => $this->stringValue('lead_assignment_rule', (string) config('aqdar.crm.assignment_rule', 'manual')),
            'maintenance_mode' => $this->boolValue('maintenance_mode', false) ? '1' : '0',
            'feature_smart_notifications' => $this->boolValue('feature_smart_notifications', false) ? '1' : '0',
            'commission_default_rate' => $this->stringValue('commission_default_rate', (string) config('aqdar.commission.default_rate', '2.5')),
            'audit_retention_months' => $this->stringValue('audit_retention_months', (string) config('aqdar.platform.audit_retention_months', 24)),
            'notification_quiet_hours' => $this->stringValue('notification_quiet_hours', (string) config('aqdar.notifications.quiet_hours', '')),
            'default_locale' => $this->stringValue('default_locale', 'ar'),
        ];
    }

    public function localizedSalesValue(string $arabicKey, string $englishKey, string $arabicConfig, string $englishConfig): string
    {
        $arabic = $this->stringValue($arabicKey, (string) config($arabicConfig));
        $english = $this->stringValue($englishKey, (string) config($englishConfig));

        if (app()->getLocale() === 'ar') {
            return $arabic !== '' ? $arabic : $english;
        }

        return $english !== '' ? $english : $arabic;
    }

    public function stringValue(string $key, string $default = ''): string
    {
        $value = $this->findByKey($key)?->value;

        return $value !== null && $value !== '' ? (string) $value : $default;
    }

    public function boolValue(string $key, bool $default = false): bool
    {
        $value = $this->findByKey($key)?->value;
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public function put(string $key, string $value, string $group = 'general'): Setting
    {
        $existing = $this->repository->findByKey($key);
        if ($existing !== null) {
            /** @var Setting $setting */
            $setting = parent::update($existing->id, ['value' => $value, 'group' => $group]);
            $this->invalidate($key);

            return $setting;
        }

        /** @var Setting $setting */
        $setting = parent::create(['key' => $key, 'value' => $value, 'group' => $group]);
        $this->invalidate($key);

        return $setting;
    }

    /** @return Collection<int, Setting> */
    public function all(): Collection
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->cache->remember('all', fn () => $this->repository->allOrdered()->toArray());

        return Setting::hydrate($rows);
    }

    public function findByKey(string $key): ?Setting
    {
        /** @var array<string, mixed>|null $row */
        $row = $this->cache->remember(
            $this->keyCacheKey($key),
            fn () => $this->repository->findByKey($key)?->toArray(),
        );

        return $row === null ? null : (new Setting)->forceFill($row)->syncOriginal();
    }

    public function create(array $data): Model
    {
        /** @var Setting $setting */
        $setting = parent::create($data);
        $this->invalidate($setting->key);

        return $setting;
    }

    public function update(int|string $id, array $data): Model
    {
        /** @var Setting|null $existing */
        $existing = $this->repository->find($id);

        abort_if($existing === null, 404);

        /** @var Setting $setting */
        $setting = parent::update($id, $data);
        $this->invalidate($existing->key);

        if (isset($data['key']) && $data['key'] !== $existing->key) {
            $this->invalidate($data['key']);
        }

        return $setting;
    }

    public function delete(int|string $id): bool
    {
        /** @var Setting|null $setting */
        $setting = $this->repository->find($id);

        if ($setting === null) {
            return false;
        }

        $deleted = parent::delete($id);

        if ($deleted) {
            $this->invalidate($setting->key);
        }

        return $deleted;
    }

    protected function invalidate(?string $key = null): void
    {
        $keys = ['all'];

        if ($key !== null) {
            $keys[] = $this->keyCacheKey($key);
        }

        $this->cache->forgetMany($keys);
    }

    protected function keyCacheKey(string $key): string
    {
        return 'key:'.$key;
    }
}
