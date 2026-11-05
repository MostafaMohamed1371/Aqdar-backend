<?php

namespace App\Modules\User\Database\Seeders;

use App\Modules\User\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'مشتري تجريبي',
                'password' => 'password',
                'phone' => '+201012345678',
                'phone_verified_at' => now(),
                'is_buyer' => true,
                'is_seller' => false,
                'locale' => 'ar',
                'consent_privacy_at' => now(),
                'consent_terms_at' => now(),
                'consent_version' => (string) config('aqdar.consent.version', '1'),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'buyer@example.com'],
            [
                'name' => 'Buyer Demo',
                'password' => 'password',
                'phone' => '+201098765432',
                'phone_verified_at' => now(),
                'is_buyer' => true,
                'is_seller' => false,
                'locale' => 'en',
                'consent_privacy_at' => now(),
                'consent_terms_at' => now(),
                'consent_version' => (string) config('aqdar.consent.version', '1'),
            ],
        );
    }
}
