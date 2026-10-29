<?php

namespace App\Modules\Listing\Jobs;

use App\Core\Enums\MediaModerationStatus;
use App\Modules\Listing\Models\ListingMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ProcessListingMediaJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $mediaId) {}

    public function handle(): void
    {
        $media = ListingMedia::query()->find($this->mediaId);

        if ($media === null || $media->moderation_status !== MediaModerationStatus::Approved) {
            return;
        }

        $bytes = Storage::disk($media->original_disk)->get($media->original_path);
        $extension = pathinfo((string) $media->original_path, PATHINFO_EXTENSION) ?: 'bin';
        $publicPath = 'listings/'.$media->listing_id.'/'.$media->id.'.'.$extension;
        Storage::disk((string) config('aqdar.files.media.public_disk'))->put($publicPath, $bytes);

        $media->forceFill(['public_path' => $publicPath])->save();
    }
}
