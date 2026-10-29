<?php

namespace App\Core\Console\Commands;

use App\Modules\Auth\Services\AuthService;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;

class JmeterPrepareCommand extends Command
{
    protected $signature = 'structer:jmeter-prepare
                            {--sync-password : Update the user password from LOGIN_PASSWORD env var}';

    protected $description = 'Prepare the JMeter load-test user (reads LOGIN_EMAIL and LOGIN_PASSWORD from env)';

    public function handle(AuthService $authService): int
    {
        $email = (string) env('LOGIN_EMAIL', '');
        $password = (string) env('LOGIN_PASSWORD', '');

        if ($email === '' || $password === '') {
            $this->components->error('Set LOGIN_EMAIL and LOGIN_PASSWORD (see qa/jmeter/credentials.env.example).');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = User::query()->create([
                'name' => 'Load Test User',
                'email' => $email,
                'password' => $password,
            ]);
            $this->components->info("Created load-test user: {$email}");
        } elseif ($this->option('sync-password')) {
            $user->password = $password;
            $user->save();
            $this->components->info("Synced password for: {$email}");
        } else {
            $this->components->info("Using existing account: {$email}");
        }

        try {
            $authService->login($email, $password);
            $this->components->info('API login verified successfully.');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->components->error('API login failed. Check LOGIN_EMAIL / LOGIN_PASSWORD or run with --sync-password.');

            return self::FAILURE;
        }
    }
}
