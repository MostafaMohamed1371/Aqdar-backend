<?php

namespace App\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupCommand extends Command
{
    protected $signature = 'aqdar:backup';

    protected $description = 'Copy private files and dump PostgreSQL when AQDAR_BACKUP_PATH is set';

    public function handle(): int
    {
        $check = $this->call('aqdar:backup-check');
        $path = (string) config('aqdar.backup.path', '');
        if ($path === '') {
            $this->comment('AQDAR_BACKUP_PATH is empty; skipped pg_dump. See docs/OPS.md.');

            return $check;
        }

        File::ensureDirectoryExists($path);
        $this->copyPrivateFiles($path);
        if (! (bool) config('aqdar.backup.dump', true)) {
            $this->comment('pg_dump skipped by AQDAR_BACKUP_DUMP.');

            return $check;
        }
        $file = rtrim($path, '/').'/aqdar_eg-'.now()->format('Y-m-d').'.dump';
        $connection = (string) config('database.default');
        if ($connection !== 'pgsql') {
            $this->warn('pg_dump skipped: database.default is '.$connection);

            return $check;
        }

        $binary = $this->pgDumpBinary();
        if ($binary === '') {
            $this->warn('pg_dump binary not found; directories were still checked.');

            return $check;
        }

        $config = config('database.connections.pgsql', []);
        $process = new Process([
            $binary,
            '-Fc',
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-p', (string) ($config['port'] ?? '5432'),
            '-U', (string) ($config['username'] ?? 'postgres'),
            '-d', (string) ($config['database'] ?? 'aqdar_eg'),
            '-f', $file,
        ]);
        $process->setTimeout(600);
        $env = getenv() ?: [];
        if (! empty($config['password'])) {
            $env['PGPASSWORD'] = (string) $config['password'];
        }
        $process->setEnv($env);
        $process->run();
        if (! $process->isSuccessful()) {
            $this->error('pg_dump failed.');

            return self::FAILURE;
        }

        $this->info('dumped '.$file);

        return $check === self::SUCCESS ? self::SUCCESS : $check;
    }

    private function pgDumpBinary(): string
    {
        foreach ([
            (string) config('aqdar.backup.pg_dump', ''),
            '/opt/homebrew/bin/pg_dump',
            '/usr/local/bin/pg_dump',
            '/usr/bin/pg_dump',
        ] as $candidate) {
            if ($candidate !== '' && is_executable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function copyPrivateFiles(string $path): void
    {
        $dest = rtrim($path, '/').'/files-'.now()->format('Y-m-d');
        foreach ([
            'documents' => storage_path('app/private/documents'),
            'media' => storage_path('app/private/media'),
        ] as $name => $source) {
            File::ensureDirectoryExists($dest.'/'.$name);
            if (File::isDirectory($source)) {
                File::copyDirectory($source, $dest.'/'.$name);
            }
        }

        $this->info('copied private files to '.$dest);
    }
}
