<?php

namespace App\Modules\Listing\Database\Seeders;

use Database\Seeders\MobileDemoSeeder;
use Illuminate\Database\Seeder;

/**
 * @deprecated Prefer MobileDemoSeeder via DatabaseSeeder.
 */
class ListingSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MobileDemoSeeder::class);
    }
}
