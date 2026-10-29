<?php

namespace App\Modules\Listing\Http\Controllers;

use App\Core\Enums\DocumentType;
use App\Core\Http\Controllers\BaseController;
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

    public function indexDocuments(Request $request): AnonymousResourceCollection
    {
        return ListingDocumentResource::collection(
            $this->files->ownerDocuments($request->user()),
        );
    }

    private function authorizeOwner(Request $request, Listing $listing): void
    {
        abort_unless($listing->user_id === $request->user()->id, 404);
    }
}
