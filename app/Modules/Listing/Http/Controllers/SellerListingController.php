<?php

namespace App\Modules\Listing\Http\Controllers;

use App\Core\Enums\ListingStatus;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Listing\Http\Requests\MyListingsIndexRequest;
use App\Modules\Listing\Http\Requests\SaveListingStepRequest;
use App\Modules\Listing\Http\Requests\StartListingRequest;
use App\Modules\Listing\Http\Requests\SubmitListingRequest;
use App\Modules\Listing\Http\Resources\ListingHistoryResource;
use App\Modules\Listing\Http\Resources\OwnerListingResource;
use App\Modules\Listing\Http\Resources\PublicListingResource;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Services\ListingService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SellerListingController extends BaseController
{
    public function __construct(private ListingService $listings) {}

    public function index(MyListingsIndexRequest $request): AnonymousResourceCollection
    {
        $query = Listing::query()
            ->where('user_id', $request->user()->id)
            ->with(['financials', 'installmentItems', 'category', 'governorate', 'area', 'developer', 'project', 'unitType'])
            ->withCount('favorites')
            ->latest();

        $status = $request->validated('status');

        if ($status === 'review') {
            $query->whereIn('status', [
                ListingStatus::Submitted,
                ListingStatus::InReview,
                ListingStatus::Resubmitted,
                ListingStatus::Approved,
            ]);
        } elseif (in_array($status, ['live', 'published'], true)) {
            $query->whereIn('status', ListingStatus::publicCases());
        } elseif ($status === 'ended') {
            $query->whereIn('status', [ListingStatus::Hold, ListingStatus::Closed, ListingStatus::Archived]);
        } elseif (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        return OwnerListingResource::collection(
            $query->paginate(min(max((int) $request->integer('per_page', 15), 1), 50)),
        )->additional(['counts' => $this->statusCounts($request->user()->id)]);
    }

    /** @return array{all: int, draft: int, review: int, needs_changes: int, live: int, rejected: int, ended: int} */
    private function statusCounts(int $userId): array
    {
        $byStatus = Listing::query()
            ->where('user_id', $userId)
            ->pluck('status')
            ->countBy(fn (ListingStatus $status) => $status->value);

        $sum = fn (array $statuses): int => collect($statuses)->sum(fn (ListingStatus $status) => $byStatus[$status->value] ?? 0);

        return [
            'all' => (int) $byStatus->sum(),
            'draft' => $sum([ListingStatus::Draft]),
            'review' => $sum([ListingStatus::Submitted, ListingStatus::InReview, ListingStatus::Resubmitted, ListingStatus::Approved]),
            'needs_changes' => $sum([ListingStatus::NeedsChanges]),
            'live' => $sum(ListingStatus::publicCases()),
            'rejected' => $sum([ListingStatus::Rejected]),
            'ended' => $sum([ListingStatus::Hold, ListingStatus::Closed, ListingStatus::Archived]),
        ];
    }

    public function store(StartListingRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $listing = $this->listings->start($user, $request->validated());
        $listing->loadCount('favorites');

        return (new OwnerListingResource($listing))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Listing $listing): OwnerListingResource
    {
        $this->authorizeOwner($request, $listing);
        $listing->load(['financials', 'installmentItems', 'category', 'governorate', 'area', 'developer', 'project', 'unitType', 'documents', 'media', 'fieldSnapshots', 'statusHistory']);
        $listing->loadCount('favorites');

        return new OwnerListingResource($listing);
    }

    public function preview(Request $request, Listing $listing): PublicListingResource
    {
        $this->authorizeOwner($request, $listing);
        $listing->load(['financials', 'installmentItems', 'governorate', 'area', 'developer', 'project', 'unitType', 'category', 'media']);

        return new PublicListingResource($listing);
    }

    public function history(Request $request, Listing $listing): AnonymousResourceCollection
    {
        $this->authorizeOwner($request, $listing);

        return ListingHistoryResource::collection($listing->statusHistory()->get());
    }

    public function saveStep(SaveListingStepRequest $request, Listing $listing, string $step): OwnerListingResource
    {
        $this->authorizeOwner($request, $listing);
        abort_unless($request->user()->can('update', $listing), 403, __('aqdar.listings.not_editable'));

        $listing = $this->listings->saveStep($listing, $step, $request->validated(), $request->user());
        $listing->loadCount('favorites');

        return new OwnerListingResource($listing);
    }

    public function submit(SubmitListingRequest $request, Listing $listing): OwnerListingResource
    {
        $this->authorizeOwner($request, $listing);
        abort_unless($request->user()->can('submit', $listing), 403, __('aqdar.listings.invalid_transition', [
            'from' => $listing->status?->value ?? '',
            'to' => 'submitted',
        ]));

        $listing = $this->listings->submit($listing, $request->user(), (bool) $request->boolean('consent'));
        $listing->loadCount('favorites');

        return new OwnerListingResource($listing);
    }

    public function resubmit(Request $request, Listing $listing): OwnerListingResource
    {
        $this->authorizeOwner($request, $listing);
        abort_unless($request->user()->can('resubmit', $listing), 403, __('aqdar.listings.invalid_transition', [
            'from' => $listing->status?->value ?? '',
            'to' => 'resubmitted',
        ]));

        $listing = $this->listings->resubmit($listing, $request->user());
        $listing->loadCount('favorites');

        return new OwnerListingResource($listing);
    }

    private function authorizeOwner(Request $request, Listing $listing): void
    {
        abort_unless($listing->user_id === $request->user()->id, 404);
    }
}
