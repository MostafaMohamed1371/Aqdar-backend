<?php

namespace App\Core\Console\Commands;

use App\Modules\Listing\Services\ListingFileService;
use Illuminate\Console\Command;

class ProcessApprovedMediaCommand extends Command
{
    protected $signature = 'aqdar:process-approved-media {--listing= : Limit to one listing id}';

    protected $description = 'Publish public_path for pending/approved listing photos that are still missing a public URL (fixes empty mobile galleries).';

    public function handle(ListingFileService $files): int
    {
        $listingId = $this->option('listing');
        $fixed = $files->repairApprovedPublicDerivatives(
            $listingId !== null && $listingId !== '' ? (int) $listingId : null,
        );

        $this->info("Published public derivatives for {$fixed} photo(s).");

        return self::SUCCESS;
    }
}
