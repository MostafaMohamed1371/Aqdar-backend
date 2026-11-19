<?php

namespace App\Modules\Notification\Services\Push;

use App\Core\Enums\DeliveryStatus;
use App\Core\Enums\NotificationChannel;
use App\Modules\Listing\Services\ContactLeakScanner;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\User\Models\UserDevice;

final class FcmConfiguration
{
    public function __construct(private ContactLeakScanner $scanner) {}

    /**
     * @return array{
     *     driver: string,
     *     ready: bool,
     *     label: string,
     *     project_id: ?string,
     *     credentials_source: string,
     *     credentials_path: ?string,
     *     credentials_readable: bool,
     *     has_client_email: bool,
     *     has_private_key: bool,
     *     devices_with_token: int,
     *     push_pending: int,
     *     push_failed: int,
     *     queue: string,
     *     checklist: list<array{key: string, ok: bool, label: string}>
     * }
     */
    public function status(): array
    {
        $driver = (string) config('aqdar.notifications.push_driver', 'log');
        $creds = $this->resolvedCredentials();
        $path = (string) config('aqdar.notifications.fcm.credentials', '');
        $pathReadable = $path !== '' && is_readable($path);
        $hasEmail = (string) config('aqdar.notifications.fcm.client_email', '') !== '';
        $hasKey = $this->normalizeKey((string) config('aqdar.notifications.fcm.private_key', '')) !== '';
        $projectId = $creds['project_id'] ?? ((string) config('aqdar.notifications.fcm.project_id', '') ?: null);
        $ready = $driver === 'fcm' && $creds !== null;

        $source = match (true) {
            $pathReadable => 'file',
            $creds !== null => 'env',
            $driver === 'fcm' => 'missing',
            default => 'log',
        };

        $devices = UserDevice::query()
            ->whereNotNull('push_token')
            ->where('push_token', '!=', '')
            ->count();

        $pending = NotificationDelivery::query()
            ->where('channel', NotificationChannel::Push)
            ->where('status', DeliveryStatus::Pending)
            ->count();

        $failed = NotificationDelivery::query()
            ->where('channel', NotificationChannel::Push)
            ->where('status', DeliveryStatus::Failed)
            ->count();

        $checklist = [
            [
                'key' => 'driver',
                'ok' => $driver === 'fcm',
                'label' => __('aqdar.notifications.fcm_check_driver'),
            ],
            [
                'key' => 'credentials',
                'ok' => $creds !== null,
                'label' => __('aqdar.notifications.fcm_check_credentials'),
            ],
            [
                'key' => 'queue',
                'ok' => true,
                'label' => __('aqdar.notifications.fcm_check_queue', [
                    'connection' => (string) config('queue.default'),
                ]),
            ],
            [
                'key' => 'devices',
                'ok' => $devices > 0,
                'label' => __('aqdar.notifications.fcm_check_devices', ['count' => $devices]),
            ],
        ];

        return [
            'driver' => $driver,
            'ready' => $ready,
            'label' => $this->label($driver, $ready),
            'project_id' => $projectId !== '' ? $projectId : null,
            'credentials_source' => $source,
            'credentials_path' => $path !== '' ? $path : null,
            'credentials_readable' => $pathReadable,
            'has_client_email' => $hasEmail || ($creds['client_email'] ?? '') !== '',
            'has_private_key' => $hasKey || ($creds['private_key'] ?? '') !== '',
            'devices_with_token' => $devices,
            'push_pending' => $pending,
            'push_failed' => $failed,
            'queue' => (string) config('queue.default'),
            'checklist' => $checklist,
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function verifyAuth(): array
    {
        if ((string) config('aqdar.notifications.push_driver', 'log') !== 'fcm') {
            return [
                'ok' => false,
                'message' => __('aqdar.notifications.fcm_verify_driver'),
            ];
        }

        $gateway = new FcmPushGateway($this->scanner);
        $result = $gateway->verifyCredentials();

        return [
            'ok' => $result,
            'message' => $result
                ? __('aqdar.notifications.fcm_verify_ok')
                : __('aqdar.notifications.fcm_verify_failed'),
        ];
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string}|null
     */
    public function resolvedCredentials(): ?array
    {
        return (new FcmPushGateway($this->scanner))->resolvedCredentials();
    }

    private function label(string $driver, bool $ready): string
    {
        if ($driver !== 'fcm') {
            return __('aqdar.settings.provider_log');
        }

        return $ready
            ? __('aqdar.settings.provider_fcm')
            : __('aqdar.settings.provider_fcm_missing');
    }

    private function normalizeKey(string $key): string
    {
        return trim(str_replace(['\\n', "\r"], ["\n", ''], $key));
    }
}
