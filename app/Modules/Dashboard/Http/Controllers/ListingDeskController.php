<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Enums\MediaModerationStatus;
use App\Core\Enums\Permission;
use App\Core\Http\Controllers\BaseController;
use App\Core\Services\QueueQuery;
use App\Modules\Dashboard\Http\Requests\AssignListingReviewerRequest;
use App\Modules\Dashboard\Http\Requests\BulkModerateListingsRequest;
use App\Modules\Dashboard\Http\Requests\ModerateListingRequest;
use App\Modules\Dashboard\Http\Requests\VerifyListingFinancialsRequest;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingMedia;
use App\Modules\Listing\Services\ListingFileService;
use App\Modules\Listing\Services\ListingModerationService;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ListingDeskController extends BaseController
{
    /** @var array<string, list<string>|null> */
    private const TABS = [
        'all' => null,
        'in_review' => ['submitted', 'in_review', 'resubmitted'],
        'needs_changes' => ['needs_changes'],
        'approved' => ['approved'],
        'published' => ['published', 'under_offer'],
        'hold' => ['hold', 'closed', 'archived'],
        'rejected' => ['rejected'],
        'draft' => ['draft'],
    ];

    public function __construct(
        private ListingModerationService $moderation,
        private ListingFileService $files,
    ) {}

    public function index(Request $request): View
    {
        $tab = (string) $request->query('status', 'all');
        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'all';
        }

        $search = trim((string) $request->query('q', ''));
        $segment = trim((string) $request->query('segment', ''));
        $alerts = $request->boolean('alerts');

        $query = Listing::query()
            ->with(['governorate', 'developer', 'financials', 'media'])
            ->latest();

        $statuses = self::TABS[$tab];
        if ($statuses !== null) {
            $query->whereIn('status', $statuses);
        }

        if (in_array($segment, ['distressed', 'non_distressed'], true)) {
            $query->where('segment', $segment);
        }

        if ($alerts) {
            $query->where('contact_flagged', true);
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('title_ar', 'ilike', $like)
                    ->orWhere('title_en', 'ilike', $like)
                    ->orWhere('public_code', 'ilike', $like)
                    ->orWhere('unit_code', 'ilike', $like)
                    ->orWhereHas('developer', function ($developer) use ($like): void {
                        $developer->where('name_ar', 'ilike', $like)
                            ->orWhere('name_en', 'ilike', $like);
                    });
            });
        }

        QueueQuery::apply($query, QueueQuery::filtersFrom($request), [
            'title' => 'title_ar',
            'market' => 'segment',
            'added' => 'created_at',
            'status' => 'status',
        ]);

        $counts = [];
        foreach (self::TABS as $key => $values) {
            $countQuery = Listing::query();
            if ($values !== null) {
                $countQuery->whereIn('status', $values);
            }
            $counts[$key] = $countQuery->count();
        }

        return view('dashboard::listings.index', [
            'listings' => $query->paginate(20)->withQueryString(),
            'tab' => $tab,
            'search' => $search,
            'segment' => $segment,
            'alerts' => $alerts,
            'counts' => $counts,
        ]);
    }

    public function show(Listing $listing): View
    {
        $listing->load([
            'financials',
            'installmentItems.evidence',
            'governorate',
            'area',
            'developer',
            'project',
            'unitType',
            'category',
            'owner',
            'documents',
            'media',
            'assignedReviewer',
            'statusHistory.actor',
        ]);

        return view('dashboard::listings.show', [
            'listing' => $listing,
            'missingApprovedDocuments' => $this->files->missingApprovedRequiredTypes($listing),
            'reviewers' => StaffUser::query()
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->whereHas('role.permissions', fn ($permission) => $permission->where('slug', Permission::ApproveListing->value))
                        ->orWhereHas('role', fn ($role) => $role->where('slug', 'super_admin'));
                })
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function assignReviewer(AssignListingReviewerRequest $request, Listing $listing): RedirectResponse
    {
        $this->moderation->assignReviewer(
            $listing,
            $request->user(),
            $request->validated('assigned_reviewer_id') !== null
                ? (int) $request->validated('assigned_reviewer_id')
                : null,
            $request->validated('reason'),
        );

        return back()->with('status', __('aqdar.listings.desk.reviewer_assigned'));
    }

    public function approve(ModerateListingRequest $request, Listing $listing): RedirectResponse
    {
        $this->moderation->approve($listing, $request->user(), (string) $request->validated('reason'));

        return back()->with('status', __('aqdar.listings.desk.approved'));
    }

    public function requestChanges(ModerateListingRequest $request, Listing $listing): RedirectResponse
    {
        $this->moderation->requestChanges(
            $listing,
            $request->user(),
            (string) $request->validated('reason'),
            (array) $request->validated('field_notes', []),
        );

        return back()->with('status', __('aqdar.listings.desk.changes_requested'));
    }

    public function reject(ModerateListingRequest $request, Listing $listing): RedirectResponse
    {
        $this->moderation->reject($listing, $request->user(), (string) $request->validated('reason'));

        return back()->with('status', __('aqdar.listings.desk.rejected'));
    }

    public function bulk(BulkModerateListingsRequest $request): RedirectResponse
    {
        $reason = (string) $request->validated('reason');
        $done = 0;
        $skipped = 0;

        Listing::query()->whereIn('id', $request->validated('ids'))->get()->each(
            function (Listing $listing) use ($request, $reason, &$done, &$skipped): void {
                try {
                    match ($request->validated('action')) {
                        'approve' => $this->moderation->approve($listing, $request->user(), $reason),
                        'request_changes' => $this->moderation->requestChanges($listing, $request->user(), $reason),
                        'reject' => $this->moderation->reject($listing, $request->user(), $reason),
                    };
                    $done++;
                } catch (ValidationException) {
                    $skipped++;
                }
            },
        );

        return back()->with('status', __('aqdar.listings.desk.bulk_result', ['done' => $done, 'skipped' => $skipped]));
    }

    public function reopen(ModerateListingRequest $request, Listing $listing): RedirectResponse
    {
        $this->moderation->reopen($listing, $request->user(), (string) $request->validated('reason'));

        return back()->with('status', __('aqdar.listings.desk.reopened'));
    }

    public function publish(ModerateListingRequest $request, Listing $listing): RedirectResponse
    {
        abort_unless($request->user()?->can('publish_listing'), 403, __('aqdar.http.forbidden'));
        $this->moderation->publish($listing, $request->user(), (string) $request->validated('reason'));

        return back()->with('status', __('aqdar.listings.desk.published'));
    }

    public function unpublish(ModerateListingRequest $request, Listing $listing): RedirectResponse
    {
        abort_unless($request->user()?->can('publish_listing'), 403, __('aqdar.http.forbidden'));
        $this->moderation->unpublish($listing, $request->user(), (string) $request->validated('reason'));

        return back()->with('status', __('aqdar.listings.desk.unpublished'));
    }

    public function underOffer(ModerateListingRequest $request, Listing $listing): RedirectResponse
    {
        abort_unless($request->user()?->can('publish_listing'), 403, __('aqdar.http.forbidden'));
        $this->moderation->markUnderOffer($listing, $request->user(), (string) $request->validated('reason'));

        return back()->with('status', __('aqdar.listings.desk.under_offer_set'));
    }

    public function hold(ModerateListingRequest $request, Listing $listing): RedirectResponse
    {
        abort_unless($request->user()?->can('publish_listing'), 403, __('aqdar.http.forbidden'));
        $this->moderation->hold($listing, $request->user(), (string) $request->validated('reason'));

        return back()->with('status', __('aqdar.listings.desk.held'));
    }

    public function close(ModerateListingRequest $request, Listing $listing): RedirectResponse
    {
        abort_unless($request->user()?->can('publish_listing'), 403, __('aqdar.http.forbidden'));
        $this->moderation->close($listing, $request->user(), (string) $request->validated('reason'));

        return back()->with('status', __('aqdar.listings.desk.closed'));
    }

    public function archive(ModerateListingRequest $request, Listing $listing): RedirectResponse
    {
        abort_unless($request->user()?->can('publish_listing'), 403, __('aqdar.http.forbidden'));
        $this->moderation->archive($listing, $request->user(), (string) $request->validated('reason'));

        return back()->with('status', __('aqdar.listings.desk.archived'));
    }

    public function verifyFinancials(VerifyListingFinancialsRequest $request, Listing $listing): RedirectResponse
    {
        $this->moderation->verifyFinancials($listing, $request->user(), $request->validated());

        return back()->with('status', __('aqdar.listings.desk.financials_verified'));
    }

    public function approveMedia(Request $request, Listing $listing, ListingMedia $media): RedirectResponse
    {
        abort_unless($media->listing_id === $listing->id, 404);
        $this->files->reviewMedia($media, $request->user(), MediaModerationStatus::Approved);

        return back()->with('status', __('aqdar.listings.desk.media_approved'));
    }

    public function rejectMedia(ModerateListingRequest $request, Listing $listing, ListingMedia $media): RedirectResponse
    {
        abort_unless($media->listing_id === $listing->id, 404);
        $this->files->reviewMedia($media, $request->user(), MediaModerationStatus::Rejected, (string) $request->validated('reason'));

        return back()->with('status', __('aqdar.listings.desk.media_rejected'));
    }
}
