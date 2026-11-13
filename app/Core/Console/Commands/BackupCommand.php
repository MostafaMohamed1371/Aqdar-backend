<?php

namespace App\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupCommand extends Command
{
    protected $signature = 'aqdar:backup';

    protected $description = 'Copy private files and dump MySQL when AQDAR_BACKUP_PATH is set';

    public function handle(): int
    {
        $check = $this->call('aqdar:backup-check');
        $path = (string) config('aqdar.backup.path', '');
        if ($path === '') {
            $this->comment('AQDAR_BACKUP_PATH is empty; skipped mysqldump. See docs/OPS.md.');

            return $check;
        }

        File::ensureDirectoryExists($path);
        $this->copyPrivateFiles($path);
        if (! (bool) config('aqdar.backup.dump', true)) {
            $this->comment('mysqldump skipped by AQDAR_BACKUP_DUMP.');

            return $check;
        }
        $file = rtrim($path, '/').'/aqdar_eg-'.now()->format('Y-m-d').'.sql';
        $connection = (string) config('database.default');
        if ($connection !== 'mysql') {
            $this->warn('mysqldump skipped: database.default is '.$connection);

            return $check;
        }

        $binary = $this->mysqlDumpBinary();
        if ($binary === '') {
            $this->warn('mysqldump binary not found; directories were still checked.');

            return $check;
        }

        $config = config('database.connections.mysql', []);
        $process = new Process([
            $binary,
            '--single-transaction',
            '--routines',
            '--triggers',
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-P', (string) ($config['port'] ?? '3306'),
            '-u', (string) ($config['username'] ?? 'root'),
            (string) ($config['database'] ?? 'aqdar_eg'),
            '--result-file='.$file,
        ]);
        $process->setTimeout(600);
        $env = getenv() ?: [];
        if (! empty($config['password'])) {
            $env['MYSQL_PWD'] = (string) $config['password'];
        }
        $process->setEnv($env);
        $process->run();
        if (! $process->isSuccessful()) {
            $this->error('mysqldump failed.');

            return self::FAILURE;
        }

        $this->info('dumped '.$file);

        return $check === self::SUCCESS ? self::SUCCESS : $check;
    }

    private function mysqlDumpBinary(): string
    {
        foreach ([
            (string) config('aqdar.backup.mysqldump', ''),
            '/opt/homebrew/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/bin/mysqldump',
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
