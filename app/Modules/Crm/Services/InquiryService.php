<?php

namespace App\Modules\Crm\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\InquiryStatus;
use App\Core\Enums\LeadStatus;
use App\Core\Enums\Permission;
use App\Core\Enums\PublicCodePrefix;
use App\Core\Identity\PublicCodeGenerator;
use App\Modules\Crm\Models\Inquiry;
use App\Modules\Crm\Models\Lead;
use App\Modules\Dashboard\Support\DeskRealtime;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Services\ContactLeakScanner;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Setting\Services\SettingService;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InquiryService
{
    public function __construct(
        private LeadService $leads,
        private PublicCodeGenerator $codes,
        private ContactLeakScanner $scanner,
        private SettingService $settings,
        private AuditLogger $audit,
        private NotificationService $notifications,
    ) {}

    public function create(User $buyer, array $data): Inquiry
    {
        $listing = Listing::query()->find($data['listing_id']);
        abort_if($listing === null || ! $listing->isPubliclyVisible(), 404);

        if ((int) $listing->user_id === (int) $buyer->id) {
            throw ValidationException::withMessages([
                'listing_id' => [__('aqdar.crm.cannot_inquire_own')],
            ]);
        }

        if (! ($data['consent'] ?? false)) {
            throw ValidationException::withMessages([
                'consent' => [__('aqdar.crm.consent_required')],
            ]);
        }

        $message = $data['message'] ?? null;
        if (is_string($message) && $message !== '' && $this->scanner->scan($message) !== null) {
            throw ValidationException::withMessages([
                'message' => [__('aqdar.crm.message_no_contact')],
            ]);
        }

        $inquiry = DB::transaction(function () use ($buyer, $listing, $data, $message): Inquiry {
            $lead = $this->leads->openOrCreateForInquiry($buyer, $listing);

            return Inquiry::query()->create([
                'public_code' => $this->codes->next(PublicCodePrefix::Inquiry),
                'user_id' => $buyer->id,
                'listing_id' => $listing->id,
                'lead_id' => $lead->id,
                'message' => $message,
                'inquiry_type' => $data['inquiry_type'] ?? 'interested',
                'contact_method' => $data['contact_method'],
                'preferred_time' => $data['preferred_time'] ?? null,
                'consent_at' => now(),
                'status' => InquiryStatus::New,
            ]);
        });

        $inquiry->loadMissing(['lead.assignee', 'listing.owner']);
        $this->leads->syncLinkedInquiries($inquiry->lead);

        $this->audit->write('inquiry.created', $buyer, $inquiry, [
            'listing_id' => $listing->id,
            'lead_id' => $inquiry->lead_id,
        ]);

        $context = [
            'listing_code' => $listing->public_code,
            'listing_id' => $listing->id,
            'lead_code' => $inquiry->lead?->public_code,
            'public_code' => $inquiry->public_code,
        ];
        $this->notifications->notifySafely($buyer, 'inquiry_created', $context, 'inquiry_created:'.$inquiry->id);
        if ($listing->owner !== null) {
            $this->notifications->notifySafely($listing->owner, 'seller_interest', $context, 'seller_interest:'.$inquiry->id);
        }
        $assignee = $inquiry->lead?->assignee;
        if ($assignee !== null) {
            $this->notifications->notifySafely(
                $assignee,
                'staff_inquiry_assigned',
                $context,
                'staff_inquiry_assigned:'.$inquiry->id.':'.$assignee->id,
            );
        } else {
            $this->notifications->notifyStaffQueue(
                'staff_unassigned_lead',
                [
                    'lead_code' => $inquiry->lead?->public_code,
                    'listing_code' => $listing->public_code,
                    'public_code' => $inquiry->public_code,
                ],
                Permission::ManageCrm,
                true,
                'staff_unassigned_lead:'.$inquiry->id,
            );
        }
        if ($inquiry->lead_id) {
            DeskRealtime::push('leads', 'inquiry', (int) $inquiry->lead_id, meta: [
                'public_code' => $inquiry->lead?->public_code,
                'status' => $inquiry->lead?->status?->value,
            ]);
        }

        return $inquiry->fresh(['listing.media', 'lead']) ?? $inquiry;
    }

    public function forBuyer(User $buyer, array $filters = []): LengthAwarePaginator
    {
        $query = Inquiry::query()
            ->where('user_id', $buyer->id)
            ->with(['listing.media', 'lead:id,public_code,status,assigned_to']);

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->latest('id')->paginate(20);
    }

    public function showForBuyer(User $buyer, Inquiry $inquiry): Inquiry
    {
        abort_unless((int) $inquiry->user_id === (int) $buyer->id, 404);

        return $inquiry->load(['listing.media', 'lead.assignee']);
    }

    public function requestCallback(User $buyer, Inquiry $inquiry): Inquiry
    {
        $inquiry = $this->showForBuyer($buyer, $inquiry);
        $lead = $inquiry->lead;
        abort_if($lead === null, 404);

        $lead->forceFill([
            'next_action' => 'callback',
            'next_action_at' => now(),
        ])->save();

        $this->audit->write('inquiry.callback_requested', $buyer, $inquiry, [
            'lead_id' => $lead->id,
        ]);

        return $inquiry->fresh(['listing.media', 'lead']) ?? $inquiry;
    }

    public function interestForListing(User $seller, Listing $listing): array
    {
        abort_unless((int) $listing->user_id === (int) $seller->id, 404);

        return $this->interestRows(
            Lead::query()->where('listing_id', $listing->id)->withCount('appointments')->orderBy('id')->get(),
        );
    }

    /**
     * Interest across every listing the seller owns; buyers stay anonymous and are numbered per listing.
     */
    public function interestForSeller(User $seller): array
    {
        $leads = Lead::query()
            ->whereIn('listing_id', Listing::query()->where('user_id', $seller->id)->select('id'))
            ->with('listing:id,public_code,title_ar,title_en')
            ->withCount('appointments')
            ->orderBy('id')
            ->get();

        $interest = $this->interestRows($leads);
        $rows = collect($interest['rows'])->sortByDesc('created_at')->values()->all();

        return ['rows' => $rows, 'counts' => $interest['counts']];
    }

    /** @param Collection<int, Lead> $leads */
    private function interestRows(Collection $leads): array
    {
        $sequence = [];

        $rows = $leads->values()->map(function (Lead $lead) use (&$sequence) {
            $sequence[$lead->listing_id] = ($sequence[$lead->listing_id] ?? 0) + 1;
            $clientStatus = match ($lead->status) {
                LeadStatus::New => 'new',
                LeadStatus::Lost, LeadStatus::Converted => 'closed',
                default => 'in_review',
            };

            return [
                'interested_buyer_seq' => $sequence[$lead->listing_id],
                'listing' => $lead->relationLoaded('listing') && $lead->listing ? [
                    'id' => $lead->listing->id,
                    'public_code' => $lead->listing->public_code,
                    'title' => $lead->listing->localizedTitle(),
                ] : null,
                'kind' => $kind = ($lead->appointments_count > 0 ? 'viewing' : 'listing_inquiry'),
                'kind_label' => __('aqdar.crm.interest_kinds.'.$kind),
                'status' => $lead->status?->value,
                'client_status' => $clientStatus,
                'status_label' => __('aqdar.crm.interest_status.'.$clientStatus),
                'created_at' => $lead->created_at,
                'offer_amount' => $lead->seller_visible_amount !== null ? (float) $lead->seller_visible_amount : null,
            ];
        });

        return [
            'rows' => $rows->all(),
            'counts' => [
                'all' => $rows->count(),
                'new' => $rows->where('client_status', 'new')->count(),
                'in_review' => $rows->where('client_status', 'in_review')->count(),
                'closed' => $rows->where('client_status', 'closed')->count(),
            ],
        ];
    }

    public function salesContact(): array
    {
        return $this->settings->publicConfig()['sales_contact'];
    }
}
