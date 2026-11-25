<?php

namespace App\Modules\Listing\Jobs;

use App\Modules\Listing\Models\ListingMedia;
use App\Modules\Listing\Services\ListingFileService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessListingMediaJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $mediaId) {}

    public function handle(ListingFileService $files): void
    {
        $media = ListingMedia::query()->find($this->mediaId);

        if ($media === null) {
            return;
        }

        $files->publishPublicDerivative($media);
    }
}
