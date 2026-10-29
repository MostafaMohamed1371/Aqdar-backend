<?php

namespace App\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupCheckCommand extends Command
{
    protected $signature = 'aqdar:backup-check';

    protected $description = 'Verify private document and media disks exist for backup drills';

    public function handle(): int
    {
        $paths = [
            'private_documents' => storage_path('app/private/documents'),
            'private_media' => storage_path('app/private/media'),
            'public_media' => storage_path('app/public/media'),
        ];

        $ok = true;
        foreach ($paths as $name => $path) {
            if (! File::isDirectory($path)) {
                File::ensureDirectoryExists($path);
            }
            $exists = File::isDirectory($path) && File::isWritable($path);
            $this->line(($exists ? 'OK' : 'FAIL').' '.$name.' '.$path);
            $ok = $ok && $exists;
        }

        $this->comment('See docs/OPS.md for PostgreSQL dump/restore drill. This command does not dump production.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
