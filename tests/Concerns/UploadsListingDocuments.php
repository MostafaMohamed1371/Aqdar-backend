<?php

namespace Tests\Concerns;

use App\Core\Enums\DocumentType;
use App\Core\Enums\MarketSegment;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;
use Illuminate\Http\UploadedFile;

trait UploadsListingDocuments
{
    protected function attachRequiredDocuments(int $listingId, bool $distressed = false): void
    {
        $types = ['national_id', 'contract', 'payment_proof'];
        if ($distressed) {
            $types[] = 'developer_status_letter';
        }
        foreach ($types as $type) {
            $this->post("/api/v1/my/listings/{$listingId}/documents", [
                'type' => $type,
                'file' => UploadedFile::fake()->create($type.'.pdf', 40, 'application/pdf'),
            ], ['Accept' => 'application/json'])->assertCreated();
        }
    }

    protected function seedRequiredDocuments(Listing $listing): void
    {
        $types = [DocumentType::NationalId, DocumentType::Contract, DocumentType::PaymentProof];
        if ($listing->segment === MarketSegment::Distressed) {
            $types[] = DocumentType::DeveloperStatusLetter;
        }

        foreach ($types as $type) {
            ListingDocument::factory()->create([
                'listing_id' => $listing->id,
                'type' => $type,
            ]);
        }
    }

    protected function seedApprovedRequiredDocuments(Listing $listing): void
    {
        $types = [DocumentType::NationalId, DocumentType::Contract, DocumentType::PaymentProof];
        if ($listing->segment === MarketSegment::Distressed) {
            $types[] = DocumentType::DeveloperStatusLetter;
        }

        foreach ($types as $type) {
            ListingDocument::factory()->approved()->create([
                'listing_id' => $listing->id,
                'type' => $type,
            ]);
        }
    }
}
