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
use App\Modules\Dashboard\Support\DeskRealtime;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingDocument;
use App\Modules\Listing\Models\ListingMedia;
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

        $inboxQuery = InboxNotification::query()
            ->where('notifiable_type', $staff->getMorphClass())
            ->where('notifiable_id', $staff->getKey());

        $inboxAfter = max(0, (int) $request->query('inbox_after', 0));
        $inboxItems = (clone $inboxQuery)
            ->when($inboxAfter > 0, fn ($query) => $query->where('id', '>', $inboxAfter))
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
            // Snapshot of current waiting queues. Client dedupes by seen IDs so draft→submit
            // (same listing id) still surfaces a toast while the desk stays open.
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
            'polled_at' => now()->toIso8601String(),
        ];

        if ($staff->can('approve_listing')) {
            $listingBase = Listing::query()->whereIn('status', [
                ListingStatus::Submitted,
                ListingStatus::InReview,
                ListingStatus::Resubmitted,
            ]);
            $payload['cursors']['listings'] = (int) ((clone $listingBase)->max('id') ?? 0);
            $payload['listings'] = (clone $listingBase)
                ->with(['governorate', 'developer'])
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->map(fn (Listing $listing) => DeskLiveRow::listing($listing))
                ->values()
                ->all();

            $mediaBase = ListingMedia::query()->whereIn('moderation_status', [
                MediaModerationStatus::Pending,
                MediaModerationStatus::Held,
            ]);
            $payload['cursors']['media'] = (int) ((clone $mediaBase)->max('id') ?? 0);
            $payload['media'] = (clone $mediaBase)
                ->with(['listing'])
                ->orderByDesc('id')
                ->limit(30)
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
            $payload['leads'] = (clone $leadsBase)
                ->with(['buyer', 'listing'])
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->map(fn (Lead $lead) => DeskLiveRow::lead($lead))
                ->values()
                ->all();

            $requestsBase = BuyerRequest::query();
            if ($salesOnly) {
                $requestsBase->where('assigned_to', $staff->id);
            }
            $payload['cursors']['requests'] = (int) ((clone $requestsBase)->max('id') ?? 0);
            $payload['requests'] = (clone $requestsBase)
                ->with(['buyer'])
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->map(fn (BuyerRequest $item) => DeskLiveRow::request($item))
                ->values()
                ->all();

            $casesBase = TransferCase::query();
            if ($salesOnly) {
                $casesBase->where('assigned_to', $staff->id);
            }
            $payload['cursors']['cases'] = (int) ((clone $casesBase)->max('id') ?? 0);
            $payload['cases'] = (clone $casesBase)
                ->with(['listing'])
                ->orderByDesc('id')
                ->limit(30)
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
            $payload['documents'] = (clone $docsBase)
                ->with(['listing'])
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->map(fn (ListingDocument $document) => DeskLiveRow::document($document))
                ->values()
                ->all();
        }

        return response()->json(['data' => $payload]);
    }

    /**
     * Fire a desk.updated event so staff can confirm Pusher (and poll toast path) without a mobile submit.
     */
    public function ping(Request $request): JsonResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();

        $code = 'PING-'.Str::upper(Str::random(4));
        $id = (int) (microtime(true) * 1000) % 2_000_000_000;

        DeskRealtime::push('listings', 'submitted', $id, meta: [
            'public_code' => $code,
            'status' => ListingStatus::Submitted->value,
            'label' => ListingStatus::Submitted->value,
            'ping' => true,
            'staff_id' => $staff->id,
            'row' => [
                'id' => $id,
                'public_code' => $code,
                'title' => __('aqdar.live_toast.ping_title'),
                'status' => ListingStatus::Submitted->value,
                'status_label' => __('aqdar.listings.status.submitted'),
                'status_tone' => 'warn',
                'url' => route('dashboard.listings.index', ['status' => 'in_review'], absolute: false),
                'view_label' => __('aqdar.listings.desk.view'),
            ],
        ]);

        return response()->json([
            'data' => [
                'ok' => true,
                'public_code' => $code,
                'id' => $id,
                'broadcast_driver' => config('broadcasting.default'),
                'message' => __('aqdar.live_toast.ping_sent'),
            ],
        ]);
    }
}
