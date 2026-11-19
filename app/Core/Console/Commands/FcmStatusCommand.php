<?php

namespace App\Core\Console\Commands;

use App\Modules\Notification\Services\Push\FcmConfiguration;
use Illuminate\Console\Command;

class FcmStatusCommand extends Command
{
    protected $signature = 'aqdar:fcm-status {--verify : Request a Google OAuth token to prove credentials work}';

    protected $description = 'Show FCM push configuration and optionally verify credentials';

    public function handle(FcmConfiguration $fcm): int
    {
        $status = $fcm->status();

        $this->table(['field', 'value'], [
            ['driver', $status['driver']],
            ['ready', $status['ready'] ? 'yes' : 'no'],
            ['label', $status['label']],
            ['project_id', $status['project_id'] ?? '—'],
            ['credentials_source', $status['credentials_source']],
            ['credentials_path', $status['credentials_path'] ?? '—'],
            ['queue', $status['queue']],
            ['devices_with_token', (string) $status['devices_with_token']],
            ['push_pending', (string) $status['push_pending']],
            ['push_failed', (string) $status['push_failed']],
        ]);

        $this->newLine();
        $this->info('Checklist');
        foreach ($status['checklist'] as $item) {
            $this->line(($item['ok'] ? '[x] ' : '[ ] ').$item['label']);
        }

        if (! $this->option('verify')) {
            return $status['ready'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $probe = $fcm->verifyAuth();
        if ($probe['ok']) {
            $this->info($probe['message']);

            return self::SUCCESS;
        }

        $this->error($probe['message']);

        return self::FAILURE;
    }
}
