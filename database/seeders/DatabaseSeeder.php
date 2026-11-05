<?php

namespace Database\Seeders;

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Notification\Database\Seeders\NotificationSeeder;
use App\Modules\Setting\Models\Setting;
use App\Modules\Staff\Database\Seeders\StaffSeeder;
use App\Modules\User\Database\Seeders\UserSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            StaffSeeder::class,
            UserSeeder::class,
            CatalogSeeder::class,
            NotificationSeeder::class,
            MobileDemoSeeder::class,
        ]);

        foreach ([
            ['platform_name', (string) config('aqdar.platform.name', 'AQDAR'), 'platform'],
            ['support_email', (string) config('aqdar.platform.support_email', 'support@aqdar.eg'), 'platform'],
            ['sales_name', (string) config('aqdar.sales.name'), 'sales'],
            ['sales_name_en', (string) config('aqdar.sales.name_en'), 'sales'],
            ['sales_phone', (string) (config('aqdar.sales.phone') ?: '+201000000001'), 'sales'],
            ['sales_hours', (string) config('aqdar.sales.hours'), 'sales'],
            ['sales_hours_en', (string) config('aqdar.sales.hours_en'), 'sales'],
            ['lead_assignment_rule', (string) config('aqdar.crm.assignment_rule', 'manual'), 'crm'],
            ['maintenance_mode', '0', 'platform'],
            ['audit_retention_months', (string) config('aqdar.platform.audit_retention_months', 24), 'platform'],
            ['commission_default_rate', (string) config('aqdar.commission.default_rate', '2.5'), 'commission'],
        ] as [$key, $value, $group]) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $group],
            );
        }
    }
}
