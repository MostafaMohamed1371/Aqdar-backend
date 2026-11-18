<?php

namespace App\Modules\Listing\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\DocumentReviewStatus;
use App\Core\Enums\DocumentType;
use App\Core\Enums\MediaModerationStatus;
use App\Core\Uploads\UploadScanner;
use App\Modules\Listing\Exceptions\RequiredDocumentsException;
use App\Modules\Listing\Jobs\ProcessListingMediaJob;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;
use App\Modules\Listing\Models\ListingMedia;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ListingFileService
{
    public function __construct(
        private ContactLeakScanner $scanner,
        private ImageProcessor $images,
        private AuditLogger $audit,
        private ListingModerationService $moderation,
        private UploadScanner $uploads,
    ) {}

    public function storeDocument(Listing $listing, User $user, DocumentType $type, UploadedFile $file): ListingDocument
    {
        $this->assertDocumentUploadAllowed($listing, $type);
        $this->uploads->assertSafe($file, (array) config('aqdar.files.documents.mimes', []));

        $latest = $listing->documents()->where('type', $type->value)->latest('version')->first();
        $version = $latest === null ? 1 : ((int) $latest->version + 1);

        $path = 'listings/'.$listing->id.'/'.$type->value.'-v'.$version.'-'.Str::random(8).'.'.$file->getClientOriginalExtension();
        $disk = (string) config('aqdar.files.documents.disk');
        Storage::disk($disk)->put($path, $file->getContent());

        $document = $listing->documents()->create([
            'type' => $type,
            'version' => $version,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $this->scanner->scan(null, $file->getClientOriginalName()) === null
                ? $file->getClientOriginalName()
                : $type->value.'-v'.$version.'.'.$file->getClientOriginalExtension(),
            'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'review_status' => DocumentReviewStatus::Pending,
        ]);

        $this->audit->write('listing.document_uploaded', $user, $document, [
            'listing_id' => $listing->id,
            'type' => $type->value,
            'version' => $version,
        ]);

        return $document;
    }

    public function storeMedia(Listing $listing, User $user, UploadedFile $file, ?string $caption = null): ListingMedia
    {
        $this->assertEditable($listing);
        $this->uploads->assertSafe($file, (array) config('aqdar.files.media.mimes', []));

        $max = (int) config('aqdar.files.media.max_count', 20);
        if ($listing->media()->count() >= $max) {
            throw ValidationException::withMessages([
                'file' => [__('aqdar.listings.media_limit', ['max' => $max])],
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: '');
        $isImage = str_starts_with($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);

        if ($isImage) {
            $bytes = $this->images->stripAndResize($file, (int) config('aqdar.files.media.max_width', 1600));
            [$width, $height] = $this->images->dimensions($bytes);
            $extension = 'jpg';
            $mime = 'image/jpeg';
        } else {
            $bytes = $file->getContent();
            $width = $height = null;
        }

        $reason = $this->scanner->scan($caption, $file->getClientOriginalName());
        $disk = (string) config('aqdar.files.media.original_disk');
        $path = 'listings/'.$listing->id.'/orig-'.Str::random(12).'.'.$extension;
        Storage::disk($disk)->put($path, $bytes);

        $media = $listing->media()->create([
            'sort_order' => ((int) $listing->media()->max('sort_order')) + 1,
            'original_disk' => $disk,
            'original_path' => $path,
            'mime' => $mime ?: 'application/octet-stream',
            'size_bytes' => strlen($bytes),
            'width' => $width,
            'height' => $height,
            'caption' => $caption,
            'moderation_status' => $reason === null ? MediaModerationStatus::Pending : MediaModerationStatus::Held,
            'hold_reason' => $reason,
        ]);

        $this->audit->write(
            $reason === null ? 'listing.media_uploaded' : 'listing.media_held',
            $user,
            $media,
            ['listing_id' => $listing->id, 'reason' => $reason],
        );

        $this->moderation->returnToReview($listing->fresh() ?? $listing, $user, 'public_media_changed');

        return $media;
    }

    public function deleteMedia(Listing $listing, ListingMedia $media, User $user): void
    {
        $this->assertEditable($listing);

        if ($media->listing_id !== $listing->id) {
            abort(404);
        }

        Storage::disk($media->original_disk)->delete($media->original_path);
        if ($media->public_path) {
            Storage::disk(config('aqdar.files.media.public_disk'))->delete($media->public_path);
        }
        $media->delete();
        $this->moderation->returnToReview($listing->fresh() ?? $listing, $user, 'public_media_changed');
    }

    /**
     * @param  list<int>  $mediaIds  every media id of the listing, in the new display order
     */
    public function reorderMedia(Listing $listing, User $user, array $mediaIds): void
    {
        $this->assertEditable($listing);

        $current = $listing->media()->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $requested = collect($mediaIds)->map(fn ($id): int => (int) $id)->sort()->values()->all();

        if ($current !== $requested) {
            throw ValidationException::withMessages([
                'ids' => [__('aqdar.listings.media_order_mismatch')],
            ]);
        }

        DB::transaction(function () use ($listing, $mediaIds): void {
            foreach (array_values($mediaIds) as $position => $mediaId) {
                $listing->media()->whereKey($mediaId)->update(['sort_order' => $position + 1]);
            }
        });

        $this->audit->write('listing.media_reordered', $user, $listing, ['order' => array_values($mediaIds)]);
    }

    /** @return list<DocumentType> */
    public function missingRequiredTypes(Listing $listing): array
    {
        $have = $listing->documents()
            ->whereIn('review_status', [
                DocumentReviewStatus::Pending->value,
                DocumentReviewStatus::Approved->value,
            ])
            ->pluck('type')
            ->map(fn ($type) => $type instanceof DocumentType ? $type : DocumentType::from((string) $type))
            ->all();

        $missing = [];
        foreach (DocumentType::cases() as $type) {
            if ($type->isRequiredFor($listing->segment) && ! in_array($type, $have, true)) {
                $missing[] = $type;
            }
        }

        return $missing;
    }

    /** @return list<DocumentType> */
    public function missingApprovedRequiredTypes(Listing $listing): array
    {
        $missing = [];
        foreach (DocumentType::cases() as $type) {
            if (! $type->isRequiredFor($listing->segment)) {
                continue;
            }

            $latest = $listing->documents()
                ->where('type', $type->value)
                ->latest('version')
                ->first();

            if ($latest === null || $latest->review_status !== DocumentReviewStatus::Approved) {
                $missing[] = $type;
            }
        }

        return $missing;
    }

    public function assertRequiredDocumentsPresent(Listing $listing): void
    {
        $missing = $this->missingRequiredTypes($listing);

        if ($missing !== []) {
            throw RequiredDocumentsException::missing($missing, $listing->id);
        }
    }

    public function assertRequiredDocumentsApproved(Listing $listing): void
    {
        $missing = $this->missingApprovedRequiredTypes($listing);

        if ($missing !== []) {
            throw RequiredDocumentsException::notApproved($missing, $listing->id);
        }
    }

    public function reviewDocument(ListingDocument $document, StaffUser $staff, DocumentReviewStatus $status, string $note): ListingDocument
    {
        $document->forceFill([
            'review_status' => $status,
            'review_note' => $note,
            'reviewed_by' => $staff->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit->write('listing.document_reviewed', $staff, $document, [
            'status' => $status->value,
            'note' => $note,
        ]);

        return $document->fresh() ?? $document;
    }

    public function reviewMedia(ListingMedia $media, StaffUser $staff, MediaModerationStatus $status, ?string $note = null): ListingMedia
    {
        if ($status !== MediaModerationStatus::Approved) {
            $this->removePublicDerivative($media);
        }

        $media->forceFill([
            'moderation_status' => $status,
            'review_note' => $note,
            'reviewed_by' => $staff->id,
            'reviewed_at' => now(),
            'public_path' => $status === MediaModerationStatus::Approved ? $media->public_path : null,
        ])->save();

        if ($status === MediaModerationStatus::Approved && ! $media->isVideo()) {
            ProcessListingMediaJob::dispatch($media->id);
        }

        $this->audit->write('listing.media_reviewed', $staff, $media, [
            'status' => $status->value,
        ]);

        return $media->fresh() ?? $media;
    }

    /** @return Collection<int, ListingDocument> */
    public function ownerDocuments(User $user)
    {
        return ListingDocument::query()
            ->whereHas('listing', fn ($query) => $query->where('user_id', $user->id))
            ->with('listing:id,public_code,status')
            ->latest()
            ->get();
    }

    private function assertDocumentUploadAllowed(Listing $listing, DocumentType $type): void
    {
        if ($listing->isEditable()) {
            return;
        }

        $latest = $listing->documents()->where('type', $type->value)->latest('version')->first();
        if ($latest !== null && in_array($latest->review_status, [
            DocumentReviewStatus::ReplacementRequested,
            DocumentReviewStatus::Rejected,
        ], true)) {
            return;
        }

        $this->failNotEditable();
    }

    private function assertEditable(Listing $listing): void
    {
        if (! $listing->isEditable()) {
            $this->failNotEditable();
        }
    }

    private function failNotEditable(): void
    {
        throw ValidationException::withMessages([
            'status' => [__('aqdar.listings.not_editable')],
        ]);
    }

    private function removePublicDerivative(ListingMedia $media): void
    {
        if (! filled($media->public_path)) {
            return;
        }

        Storage::disk((string) config('aqdar.files.media.public_disk'))->delete($media->public_path);
    }
}
