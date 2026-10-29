<?php

namespace App\Modules\Listing\Database\Factories;

use App\Core\Enums\MediaModerationStatus;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ListingMedia> */
class ListingMediaFactory extends Factory
{
    protected $model = ListingMedia::class;

    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'sort_order' => 1,
            'original_disk' => 'private_media',
            'original_path' => 'listings/1/original.jpg',
            'public_path' => null,
            'mime' => 'image/jpeg',
            'size_bytes' => 80000,
            'width' => 800,
            'height' => 600,
            'moderation_status' => MediaModerationStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'moderation_status' => MediaModerationStatus::Approved,
            'public_path' => 'listings/1/public.jpg',
            'reviewed_at' => now(),
        ]);
    }
}
