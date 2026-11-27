<?php

namespace App\Modules\Listing\Http\Controllers;

use App\Core\Enums\DocumentType;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Listing\Http\Requests\ReorderListingMediaRequest;
use App\Modules\Listing\Http\Requests\UploadListingDocumentRequest;
use App\Modules\Listing\Http\Requests\UploadListingMediaRequest;
use App\Modules\Listing\Http\Resources\ListingDocumentResource;
use App\Modules\Listing\Http\Resources\ListingMediaResource;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingMedia;
use App\Modules\Listing\Services\ListingFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerFileController extends BaseController
{
    public function __construct(private ListingFileService $files) {}

    public function storeDocument(UploadListingDocumentRequest $request, Listing $listing): JsonResponse
    {
        $this->authorizeOwner($request, $listing);
        $document = $this->files->storeDocument(
            $listing,
            $request->user(),
            DocumentType::from((string) $request->validated('type')),
            $request->file('file'),
        );

        return (new ListingDocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    public function storeMedia(UploadListingMediaRequest $request, Listing $listing): JsonResponse
    {
        $this->authorizeOwner($request, $listing);
        $media = $this->files->storeMedia(
            $listing,
            $request->user(),
            $request->file('file'),
            $request->validated('caption') ?? null,
        );

        return (new ListingMediaResource($media))
            ->response()
            ->setStatusCode(201);
    }

    public function destroyMedia(Request $request, Listing $listing, ListingMedia $media): JsonResponse
    {
        $this->authorizeOwner($request, $listing);
        $this->files->deleteMedia($listing, $media, $request->user());

        return response()->json(['message' => __('aqdar.listings.media_removed')]);
    }

    public function reorderMedia(ReorderListingMediaRequest $request, Listing $listing): AnonymousResourceCollection
    {
        $this->authorizeOwner($request, $listing);
        $this->files->reorderMedia($listing, $request->user(), $request->mediaIds());

        return ListingMediaResource::collection($listing->media()->orderBy('sort_order')->get());
    }

    public function indexDocuments(Request $request): AnonymousResourceCollection
    {
        return ListingDocumentResource::collection(
            $this->files->ownerDocuments($request->user()),
        );
    }

    /**
     * Short-lived signed stream of a private original so the seller gallery can render
     * pending photos/videos before staff approval (public gallery still waits for approve).
     */
    public function previewMedia(Request $request, Listing $listing, ListingMedia $media): StreamedResponse
    {
        abort_unless((int) $media->listing_id === (int) $listing->id, 404);
        abort_unless(filled($media->original_path), 404);
        abort_unless(Storage::disk($media->original_disk)->exists($media->original_path), 404);

        return Storage::disk($media->original_disk)->response(
            $media->original_path,
            null,
            [
                'Content-Type' => $media->mime ?: 'application/octet-stream',
                'Cache-Control' => 'private, max-age=300',
            ],
        );
    }

    private function authorizeOwner(Request $request, Listing $listing): void
    {
        abort_unless($listing->user_id === $request->user()->id, 404);
    }
}
