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
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeskLiveController extends BaseController
{
    public function __construct(
        private DashboardService $dashboard,
        private LeadService $leads,
        private NotificationService $notifications,
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

        $inboxAfter = max(0, (int) $request->query('inbox_after', 0));
        $inboxQuery = InboxNotification::query()
            ->where('notifiable_type', $staff->getMorphClass())
            ->where('notifiable_id', $staff->getKey());

        $inboxItems = (clone $inboxQuery)
            ->where('id', '>', $inboxAfter)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (InboxNotification $notification): array => [
                'id' => $notification->id,
                'event_key' => $notification->event_key,
                'title' => $notification->localizedTitle(),
                'body' => $notification->localizedBody(),
                'created_at' => optional($notification->created_at)?->toIso8601String(),
            ])
            ->values()
            ->all();

        $payload = [
            'listings' => [],
            'leads' => [],
            'requests' => [],
            'cases' => [],
            'documents' => [],
            'media' => [],
            'badges' => $badges,
            'needs' => $this->dashboard->waitingActions(),
            'cursors' => [
                'listings' => 0,
                'leads' => 0,
                'requests' => 0,
                'cases' => 0,
                'documents' => 0,
                'media' => 0,
                'inbox' => (int) ((clone $inboxQuery)->max('id') ?? 0),
            ],
            'inbox' => [
                'unread_count' => $this->notifications->unreadCount($staff),
                'items' => $inboxItems,
            ],
        ];

        if ($staff->can('approve_listing')) {
            $listingBase = Listing::query()->whereIn('status', [
                ListingStatus::Submitted,
                ListingStatus::InReview,
                ListingStatus::Resubmitted,
            ]);
            $payload['cursors']['listings'] = (int) ((clone $listingBase)->max('id') ?? 0);

            $after = max(0, (int) $request->query('listings_after', 0));
            $payload['listings'] = (clone $listingBase)
                ->with(['governorate', 'developer'])
                ->where('id', '>', $after)
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (Listing $listing) => DeskLiveRow::listing($listing))
                ->values()
                ->all();

            $mediaBase = ListingMedia::query()->whereIn('moderation_status', [
                MediaModerationStatus::Pending,
                MediaModerationStatus::Held,
            ]);
            $payload['cursors']['media'] = (int) ((clone $mediaBase)->max('id') ?? 0);

            $mediaAfter = max(0, (int) $request->query('media_after', 0));
            $payload['media'] = (clone $mediaBase)
                ->with(['listing'])
                ->where('id', '>', $mediaAfter)
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (ListingMedia $media) => DeskLiveRow::media($media))
                ->values()
                ->all();
        }

        if ($staff->can('manage_crm')) {
            $salesOnly = ! $this->leads->canManageAll($staff) && $staff->role?->slug === 'sales';

            $leadsBase = Lead::query();
            if ($salesOnly) {
                $leadsBase->where('assigned_to', $staff->id);
            }
            $payload['cursors']['leads'] = (int) ((clone $leadsBase)->max('id') ?? 0);

            $leadsAfter = max(0, (int) $request->query('leads_after', 0));
            $payload['leads'] = (clone $leadsBase)
                ->with(['buyer', 'listing'])
                ->where('id', '>', $leadsAfter)
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (Lead $lead) => DeskLiveRow::lead($lead))
                ->values()
                ->all();

            $requestsBase = BuyerRequest::query();
            if ($salesOnly) {
                $requestsBase->where('assigned_to', $staff->id);
            }
            $payload['cursors']['requests'] = (int) ((clone $requestsBase)->max('id') ?? 0);

            $requestsAfter = max(0, (int) $request->query('requests_after', 0));
            $payload['requests'] = (clone $requestsBase)
                ->with(['buyer'])
                ->where('id', '>', $requestsAfter)
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (BuyerRequest $item) => DeskLiveRow::request($item))
                ->values()
                ->all();

            $casesBase = TransferCase::query();
            if ($salesOnly) {
                $casesBase->where('assigned_to', $staff->id);
            }
            $payload['cursors']['cases'] = (int) ((clone $casesBase)->max('id') ?? 0);

            $casesAfter = max(0, (int) $request->query('cases_after', 0));
            $payload['cases'] = (clone $casesBase)
                ->with(['listing'])
                ->where('id', '>', $casesAfter)
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (TransferCase $case) => DeskLiveRow::transferCase($case))
                ->values()
                ->all();
        }

        if ($staff->can('open_private_documents')) {
            $docsBase = ListingDocument::query()->whereIn('review_status', [
                DocumentReviewStatus::Pending,
                DocumentReviewStatus::ReplacementRequested,
            ]);
            $payload['cursors']['documents'] = (int) ((clone $docsBase)->max('id') ?? 0);

            $docsAfter = max(0, (int) $request->query('documents_after', 0));
            $payload['documents'] = (clone $docsBase)
                ->with(['listing'])
                ->where('id', '>', $docsAfter)
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
