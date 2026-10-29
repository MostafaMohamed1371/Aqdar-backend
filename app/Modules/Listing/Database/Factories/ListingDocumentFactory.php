<?php

namespace App\Modules\Listing\Database\Factories;

use App\Core\Enums\DocumentReviewStatus;
use App\Core\Enums\DocumentType;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ListingDocument> */
class ListingDocumentFactory extends Factory
{
    protected $model = ListingDocument::class;

    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'type' => DocumentType::NationalId,
            'version' => 1,
            'disk' => 'private_documents',
            'path' => 'listings/1/national_id-v1.pdf',
            'original_name' => 'national_id.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 12000,
            'review_status' => DocumentReviewStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'review_status' => DocumentReviewStatus::Approved,
            'review_note' => 'approved',
            'reviewed_at' => now(),
        ]);
    }
}
