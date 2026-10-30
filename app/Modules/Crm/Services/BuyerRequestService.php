<?php

namespace App\Modules\Crm\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\BuyerRequestStatus;
use App\Core\Enums\MatchBuyerResponse;
use App\Core\Enums\Permission;
use App\Core\Enums\PublicCodePrefix;
use App\Core\Enums\SegmentPreference;
use App\Core\Identity\PublicCodeGenerator;
use App\Core\Services\QueueQuery;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\RequestMatch;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Services\ContactLeakScanner;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerRequestService
{
    public function __construct(
        private LeadService $leads,
        private RequestMatchService $matcher,
        private CaseService $cases,
        private PublicCodeGenerator $codes,
        private ContactLeakScanner $scanner,
        private AuditLogger $audit,
        private NotificationService $notifications,
    ) {}

    public function create(User $buyer, array $data): BuyerRequest
    {
        if (! ($data['consent'] ?? false)) {
            throw ValidationException::withMessages([
                'consent' => [__('aqdar.crm.consent_required')],
            ]);
        }

        $priorities = $data['priorities'] ?? null;
        if (is_string($priorities) && $priorities !== '' && $this->scanner->scan($priorities) !== null) {
            throw ValidationException::withMessages([
                'priorities' => [__('aqdar.crm.message_no_contact')],
            ]);
        }

        $request = DB::transaction(function () use ($buyer, $data, $priorities): BuyerRequest {
            $lead = $this->leads->openForCustomRequest($buyer);
            $duplicateOf = $this->findOpenDuplicate($buyer, $data);
            $request = BuyerRequest::query()->create([
                'public_code' => $this->codes->next(PublicCodePrefix::BuyerRequest),
                'user_id' => $buyer->id,
                'lead_id' => $lead->id,
                'status' => BuyerRequestStatus::New,
                'is_duplicate' => $duplicateOf !== null,
                'duplicate_of_id' => $duplicateOf?->id,
                'intent' => $data['intent'],
                'segment_preference' => $data['segment_preference'] ?? 'any',
                'unit_type_id' => $data['unit_type_id'] ?? null,
                'governorate_id' => $data['governorate_id'] ?? null,
                'area_id' => $data['area_id'] ?? null,
                'developer_id' => $data['developer_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'area_sqm_min' => $data['area_sqm_min'] ?? null,
                'area_sqm_max' => $data['area_sqm_max'] ?? null,
                'bedrooms' => $data['bedrooms'] ?? null,
                'finishing' => $data['finishing'] ?? null,
                'budget_min' => $data['budget_min'] ?? null,
                'budget_max' => $data['budget_max'] ?? null,
                'down_payment' => $data['down_payment'] ?? null,
                'monthly_installment' => $data['monthly_installment'] ?? null,
                'duration_years' => $data['duration_years'] ?? null,
                'timeline' => $data['timeline'] ?? null,
                'priorities' => $priorities,
                'contact_method' => $data['contact_method'],
                'consent_at' => now(),
                'criteria' => $data,
            ]);
            $this->recordHistory($request, null, BuyerRequestStatus::New, null, 'created');
            $this->matcher->rematch($request);

            return $request;
        });

        $this->audit->write('buyer_request.created', $buyer, $request, [
            'matches' => $request->matches()->count(),
        ]);
        $this->notifications->notifySafely($buyer, 'buyer_request_created', [
            'public_code' => $request->public_code,
        ]);
        $this->notifications->notifyStaffQueue(
            'staff_new_buyer_request',
            ['public_code' => $request->public_code],
            Permission::ManageCrm,
        );

        return $request->fresh(['matches' => fn ($query) => $query->whereNotNull('sent_at')->with(['listing.media', 'listing.financials']), 'lead', 'assignee']) ?? $request;
    }

    public function createManual(StaffUser $staff, array $data): BuyerRequest
    {
        $buyer = User::query()->findOrFail($data['buyer_id']);
        $payload = $data;
        $payload['consent'] = true;
        $payload['contact_method'] = $data['contact_method'] ?? 'phone';
        $payload['intent'] = $data['intent'] ?? 'living';
        $request = $this->create($buyer, $payload);
        if (! empty($data['assigned_to'])) {
            $request = $this->assign($request, $staff, (int) $data['assigned_to']);
        }

        return $request;
    }

    public function forBuyer(User $buyer): LengthAwarePaginator
    {
        return BuyerRequest::query()
            ->where('user_id', $buyer->id)
            ->with(['matches' => fn ($query) => $query->whereNotNull('sent_at')->with(['listing.media', 'listing.financials']), 'assignee'])
            ->latest('id')
            ->paginate(20);
    }

    public function showForBuyer(User $buyer, BuyerRequest $request): BuyerRequest
    {
        abort_unless((int) $request->user_id === (int) $buyer->id, 404);

        return $request->load([
            'matches' => fn ($query) => $query->whereNotNull('sent_at')->with(['listing.media', 'listing.financials', 'listing.governorate', 'listing.area']),
            'history',
            'assignee',
            'lead',
            'unitType',
            'governorate',
            'area',
            'developer',
            'project',
        ]);
    }

    public function requestCallback(User $buyer, BuyerRequest $request): BuyerRequest
    {
        $request = $this->showForBuyer($buyer, $request);
        if ($request->lead) {
            $request->lead->forceFill([
                'next_action' => 'callback',
                'next_action_at' => now(),
            ])->save();
        }
        $this->audit->write('buyer_request.callback_requested', $buyer, $request, []);

        return $request->fresh(['matches' => fn ($query) => $query->whereNotNull('sent_at')->with(['listing.media']), 'assignee', 'lead']) ?? $request;
    }

    public function paginateFor(StaffUser $staff, array $filters): LengthAwarePaginator
    {
        $query = BuyerRequest::query()
            ->with(['buyer', 'assignee', 'unitType', 'area', 'governorate'])
            ->latest('id');
        $this->restrictToAssigned($query, $staff);

        $status = (string) ($filters['status'] ?? '');
        if ($status === 'closed') {
            $query->whereIn('status', [
                BuyerRequestStatus::Converted->value,
                BuyerRequestStatus::ClosedNoMatch->value,
            ]);
        } elseif ($status === 'in_review') {
            $query->whereIn('status', [
                BuyerRequestStatus::New->value,
                BuyerRequestStatus::Assigned->value,
                BuyerRequestStatus::Matching->value,
            ]);
        } elseif ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }
        $type = (string) ($filters['type'] ?? '');
        if ($type === 'investment') {
            $query->where('intent', 'investment');
        } elseif (in_array($type, ['apartment', 'villa', 'land'], true)) {
            $query->whereHas('unitType', function (Builder $inner) use ($type): void {
                $inner->where('slug', 'ilike', '%'.$type.'%')
                    ->orWhere('name_en', 'ilike', '%'.$type.'%')
                    ->orWhere('name_ar', 'ilike', '%'.$this->arabicType($type).'%');
            });
        }
        $assignedTo = (string) ($filters['assigned_to'] ?? '');
        if ($assignedTo === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($assignedTo !== '') {
            $query->where('assigned_to', (int) $assignedTo);
        }
        if (is_numeric($filters['governorate_id'] ?? null)) {
            $query->where('governorate_id', (int) $filters['governorate_id']);
        }
        if (in_array($filters['segment'] ?? '', array_column(SegmentPreference::cases(), 'value'), true)) {
            $query->where('segment_preference', $filters['segment']);
        }
        if (is_numeric($filters['budget_min'] ?? null)) {
            $query->where(fn (Builder $inner) => $inner->whereNull('budget_max')->orWhere('budget_max', '>=', (float) $filters['budget_min']));
        }
        if (is_numeric($filters['budget_max'] ?? null)) {
            $query->where(fn (Builder $inner) => $inner->whereNull('budget_min')->orWhere('budget_min', '<=', (float) $filters['budget_max']));
        }
        if (! empty($filters['overdue'])) {
            $query->whereHas('lead', fn (Builder $lead) => $lead->overdueFollowUp());
        }
        if (! empty($filters['duplicates'])) {
            $query->where('is_duplicate', true);
        }
        if (! empty($filters['q'])) {
            $like = '%'.addcslashes((string) $filters['q'], '%_\\').'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('public_code', 'ilike', $like)
                    ->orWhereHas('buyer', fn ($buyer) => $buyer->where('name', 'ilike', $like)->orWhere('phone', 'ilike', $like));
            });
        }
        QueueQuery::apply($query, $filters, [
            'code' => 'public_code',
            'budget' => 'budget_max',
            'status' => 'status',
            'created' => 'created_at',
        ]);

        return $query->paginate(20)->withQueryString();
    }

    /** @return array<string, int> */
    public function countsFor(StaffUser $staff): array
    {
        $base = BuyerRequest::query();
        $this->restrictToAssigned($base, $staff);
        $counts = ['all' => (clone $base)->count()];
        foreach (BuyerRequestStatus::cases() as $status) {
            $counts[$status->value] = (clone $base)->where('status', $status->value)->count();
        }
        $counts['in_review'] = (clone $base)->whereIn('status', [
            BuyerRequestStatus::New->value,
            BuyerRequestStatus::Assigned->value,
            BuyerRequestStatus::Matching->value,
        ])->count();
        $counts['contacted'] = (clone $base)->where('status', BuyerRequestStatus::ContactingBuyer->value)->count();
        $counts['sent'] = (clone $base)->where('status', BuyerRequestStatus::OptionsShared->value)->count();
        $counts['closed'] = (clone $base)->whereIn('status', [
            BuyerRequestStatus::Converted->value,
            BuyerRequestStatus::ClosedNoMatch->value,
        ])->count();

        return $counts;
    }

    public function showForStaff(StaffUser $staff, BuyerRequest $request): BuyerRequest
    {
        $this->leads->assertCanAct($staff, $request->lead ?? $this->syntheticLead($request));

        return $request->load([
            'buyer',
            'assignee',
            'lead.activities',
            'duplicateOf',
            'unitType',
            'governorate',
            'area',
            'developer',
            'project',
            'matches.listing.financials',
            'matches.listing.media',
            'history.actor',
        ]);
    }

    public function assign(BuyerRequest $request, StaffUser $actor, int $assigneeId, ?string $reason = null): BuyerRequest
    {
        $this->leads->assertCanManageAll($actor);
        $this->leads->assertReassignReason($request->assigned_to, $assigneeId, $reason);
        if ($request->lead) {
            $this->leads->assign($request->lead, $actor, $assigneeId, $reason ?? 'buyer_request:'.$request->public_code);
        }
        $this->setStatus($request, $actor, BuyerRequestStatus::Assigned, $reason ?? 'assigned');
        $request->forceFill(['assigned_to' => $assigneeId])->save();

        return $request->fresh(['assignee', 'lead']) ?? $request;
    }

    public function setStatus(BuyerRequest $request, StaffUser $actor, BuyerRequestStatus $status, string $reason): BuyerRequest
    {
        $lead = $request->lead ?? $this->syntheticLead($request);
        $this->leads->assertCanAct($actor, $lead);
        $old = $request->status;
        $request->forceFill(['status' => $status])->save();
        $this->recordHistory($request, $old, $status, $actor, $reason);
        $this->audit->write('buyer_request.status', $actor, $request, [
            'status' => $status->value,
        ]);
        if ($request->buyer !== null) {
            $this->notifications->notifySafely($request->buyer, 'buyer_request_updated', [
                'public_code' => $request->public_code,
            ]);
        }

        return $request->fresh() ?? $request;
    }

    public function refreshMatches(BuyerRequest $request, StaffUser $actor): BuyerRequest
    {
        $this->showForStaff($actor, $request);
        $this->matcher->rematch($request);
        if ($request->status === BuyerRequestStatus::New || $request->status === BuyerRequestStatus::Assigned) {
            $this->setStatus($request, $actor, BuyerRequestStatus::Matching, 'rematch');
        }

        return $request->fresh(['matches.listing.financials', 'matches.listing.media']) ?? $request;
    }

    public function attachListing(BuyerRequest $request, StaffUser $actor, string $code): RequestMatch
    {
        $this->showForStaff($actor, $request);
        $listing = Listing::query()->where('public_code', trim($code))->first();
        if ($listing === null) {
            throw ValidationException::withMessages([
                'listing_code' => [__('aqdar.requests.listing_not_found')],
            ]);
        }

        $match = $this->matcher->attach($request, $listing);
        $this->audit->write('buyer_request.match_attached', $actor, $request, [
            'listing_id' => $listing->id,
            'score' => $match->score,
        ]);

        return $match;
    }

    public function sendMatch(RequestMatch $match, StaffUser $actor): RequestMatch
    {
        $request = $match->request;
        abort_if($request === null, 404);
        $this->showForStaff($actor, $request);
        $match->forceFill(['sent_at' => now()])->save();
        if (in_array($request->status, [BuyerRequestStatus::New, BuyerRequestStatus::Assigned, BuyerRequestStatus::Matching, BuyerRequestStatus::ContactingBuyer], true)) {
            $this->setStatus($request, $actor, BuyerRequestStatus::OptionsShared, 'options_sent');
        }
        $this->audit->write('buyer_request.match_sent', $actor, $match, [
            'listing_id' => $match->listing_id,
        ]);

        return $match->fresh() ?? $match;
    }

    public function recordMatchResponse(RequestMatch $match, StaffUser $actor, MatchBuyerResponse $response): RequestMatch
    {
        $request = $match->request;
        abort_if($request === null, 404);
        $this->showForStaff($actor, $request);
        $match->forceFill(['buyer_response' => $response])->save();
        $this->audit->write('buyer_request.match_response', $actor, $match, [
            'response' => $response->value,
        ]);

        return $match->fresh() ?? $match;
    }

    public function openLeadFromMatch(RequestMatch $match, StaffUser $actor): Lead
    {
        $request = $match->request;
        abort_if($request === null || $match->listing === null, 404);
        $this->showForStaff($actor, $request);
        $listing = Listing::query()->findOrFail($match->listing_id);
        $buyer = $request->buyer;
        abort_if($buyer === null, 404);

        return $this->leads->openOrCreateForInquiry($buyer, $listing);
    }

    public function convertToCase(BuyerRequest $request, StaffUser $actor, string $reason, ?RequestMatch $match = null): TransferCase
    {
        $this->showForStaff($actor, $request);
        abort_if($match !== null && (int) $match->buyer_request_id !== (int) $request->id, 404);
        $case = $this->cases->openFromBuyerRequest($request, $actor, $reason, $match);
        if ($request->status !== BuyerRequestStatus::Converted) {
            $this->setStatus($request, $actor, BuyerRequestStatus::Converted, $reason);
        }
        if ($request->lead) {
            $this->leads->convert($request->lead, $actor, $reason, createCase: false);
        }

        return $case;
    }

    public function canManageAll(StaffUser $staff): bool
    {
        return $this->leads->canManageAll($staff);
    }

    public function assignableStaff()
    {
        return $this->leads->assignableStaff();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findOpenDuplicate(User $buyer, array $data): ?BuyerRequest
    {
        $query = BuyerRequest::query()
            ->where('user_id', $buyer->id)
            ->where('intent', $data['intent'])
            ->whereNotIn('status', [BuyerRequestStatus::Converted->value, BuyerRequestStatus::ClosedNoMatch->value])
            ->where('created_at', '>=', now()->subDays(30));
        foreach (['unit_type_id', 'governorate_id'] as $column) {
            $value = $data[$column] ?? null;
            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        return $query->oldest('id')->first();
    }

    private function recordHistory(
        BuyerRequest $request,
        ?BuyerRequestStatus $old,
        BuyerRequestStatus $new,
        ?StaffUser $actor,
        string $reason,
    ): void {
        $request->history()->create([
            'actor_id' => $actor?->id,
            'old_status' => $old?->value,
            'new_status' => $new->value,
            'reason' => $reason,
        ]);
    }

    private function restrictToAssigned(Builder $query, StaffUser $staff): void
    {
        if (! $this->leads->canManageAll($staff) && $staff->role?->slug === 'sales') {
            $query->where('assigned_to', $staff->id);
        }
    }

    private function syntheticLead(BuyerRequest $request): Lead
    {
        $lead = new Lead;
        $lead->id = 0;
        $lead->assigned_to = $request->assigned_to;

        return $lead;
    }

    private function arabicType(string $type): string
    {
        return match ($type) {
            'apartment' => 'شقة',
            'villa' => 'فيلا',
            'land' => 'أرض',
            default => $type,
        };
    }
}
