<?php

namespace App\Modules\Listing\Jobs;

use App\Modules\Listing\Services\SavedSearchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MatchSavedSearchesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $listingId, public string $kind = 'match') {}

    public function handle(SavedSearchService $savedSearches): void
    {
        if ($this->kind === 'price_drop') {
            $savedSearches->matchPriceDrop($this->listingId);

            return;
        }

        $savedSearches->matchListing($this->listingId);
    }
}
