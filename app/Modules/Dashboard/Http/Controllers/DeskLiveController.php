<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Enums\DocumentReviewStatus;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\MediaModerationStatus;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Crm\Services\LeadService;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Dashboard\Support\DeskLiveRow;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;
use App\Modules\Listing\Models\ListingMedia;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeskLiveController extends BaseController
{
    public function __construct(
        private DashboardService $dashboard,
        private LeadService $leads,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();

        $badges = $this->dashboard->queueBadges();
        $badges['media_pending'] = ListingMedia::query()
            ->whereIn('moderation_status', [
                MediaModerationStatus::Pending,
                MediaModerationStatus::Held,
            ])
            ->count();

        $payload = [
            'listings' => [],
            'leads' => [],
            'requests' => [],
            'cases' => [],
            'documents' => [],
            'badges' => $badges,
        ];

        if ($staff->can('approve_listing')) {
            $after = max(0, (int) $request->query('listings_after', 0));
            $payload['listings'] = Listing::query()
                ->with(['governorate', 'developer'])
                ->where('id', '>', $after)
                ->whereIn('status', [
                    ListingStatus::Submitted,
                    ListingStatus::InReview,
                    ListingStatus::Resubmitted,
                ])
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (Listing $listing) => DeskLiveRow::listing($listing))
                ->values()
                ->all();
        }

        if ($staff->can('manage_crm')) {
            $salesOnly = ! $this->leads->canManageAll($staff) && $staff->role?->slug === 'sales';

            $leadsAfter = max(0, (int) $request->query('leads_after', 0));
            $leadsQuery = Lead::query()->with(['buyer', 'listing'])->where('id', '>', $leadsAfter);
            if ($salesOnly) {
                $leadsQuery->where('assigned_to', $staff->id);
            }
            $payload['leads'] = $leadsQuery
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (Lead $lead) => DeskLiveRow::lead($lead))
                ->values()
                ->all();

            $requestsAfter = max(0, (int) $request->query('requests_after', 0));
            $requestsQuery = BuyerRequest::query()->with(['buyer'])->where('id', '>', $requestsAfter);
            if ($salesOnly) {
                $requestsQuery->where('assigned_to', $staff->id);
            }
            $payload['requests'] = $requestsQuery
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (BuyerRequest $item) => DeskLiveRow::request($item))
                ->values()
                ->all();

            $casesAfter = max(0, (int) $request->query('cases_after', 0));
            $casesQuery = TransferCase::query()->with(['listing'])->where('id', '>', $casesAfter);
            if ($salesOnly) {
                $casesQuery->where('assigned_to', $staff->id);
            }
            $payload['cases'] = $casesQuery
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (TransferCase $case) => DeskLiveRow::transferCase($case))
                ->values()
                ->all();
        }

        if ($staff->can('open_private_documents')) {
            $docsAfter = max(0, (int) $request->query('documents_after', 0));
            $payload['documents'] = ListingDocument::query()
                ->with(['listing'])
                ->where('id', '>', $docsAfter)
                ->whereIn('review_status', [
                    DocumentReviewStatus::Pending,
                    DocumentReviewStatus::ReplacementRequested,
                ])
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (ListingDocument $document) => DeskLiveRow::document($document))
                ->values()
                ->all();
        }

        return response()->json(['data' => $payload]);
    }
}
