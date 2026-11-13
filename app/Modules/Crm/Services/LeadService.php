<?php

namespace App\Modules\Crm\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\InquiryStatus;
use App\Core\Enums\LeadPriority;
use App\Core\Enums\LeadSource;
use App\Core\Enums\LeadStatus;
use App\Core\Enums\PublicCodePrefix;
use App\Core\Enums\StaffRole;
use App\Core\Identity\PublicCodeGenerator;
use App\Core\Services\QueueQuery;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadActivity;
use App\Modules\Listing\Models\Listing;
use App\Modules\Setting\Services\SettingService;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function __construct(
        private PublicCodeGenerator $codes,
        private AuditLogger $audit,
        private SettingService $settings,
    ) {}

    public function findOpenForBuyerListing(int $buyerId, int $listingId): ?Lead
    {
        return Lead::query()
            ->where('buyer_id', $buyerId)
            ->where('listing_id', $listingId)
            ->whereNotIn('status', [LeadStatus::Converted->value, LeadStatus::Lost->value])
            ->latest('id')
            ->first();
    }

    public function openOrCreateForInquiry(User $buyer, Listing $listing): Lead
    {
        $previousClosed = Lead::query()
            ->where('buyer_id', $buyer->id)
            ->where('listing_id', $listing->id)
            ->whereIn('status', [LeadStatus::Converted->value, LeadStatus::Lost->value])
            ->latest('id')
            ->first();

        $existing = $this->findOpenForBuyerListing($buyer->id, $listing->id);
        if ($existing !== null) {
            $existing->forceFill([
                'is_duplicate' => true,
                'duplicate_of_id' => $existing->duplicate_of_id ?? $previousClosed?->id,
            ])->save();

            return $existing;
        }

        $lead = Lead::query()->create([
            'public_code' => $this->codes->next(PublicCodePrefix::Lead),
            'source' => LeadSource::ListingInquiry,
            'buyer_id' => $buyer->id,
            'seller_id' => $listing->user_id,
            'listing_id' => $listing->id,
            'status' => LeadStatus::New,
            'priority' => LeadPriority::Medium,
            'next_action' => 'first_call',
            'sla_due_at' => now()->addHours((int) config('aqdar.crm.sla_hours', 2)),
            'is_duplicate' => $previousClosed !== null,
            'duplicate_of_id' => $previousClosed?->id,
        ]);
        $this->autoAssign($lead);
        $this->syncLinkedInquiries($lead);

        return $lead;
    }

    public function openForCustomRequest(User $buyer): Lead
    {
        $lead = Lead::query()->create([
            'public_code' => $this->codes->next(PublicCodePrefix::Lead),
            'source' => LeadSource::CustomRequest,
            'buyer_id' => $buyer->id,
            'status' => LeadStatus::New,
            'priority' => LeadPriority::Medium,
            'next_action' => 'first_call',
            'sla_due_at' => now()->addHours((int) config('aqdar.crm.sla_hours', 2)),
            'is_duplicate' => false,
        ]);
        $this->audit->write('lead.created', $buyer, $lead, ['source' => LeadSource::CustomRequest->value]);
        $this->autoAssign($lead);
        $this->syncLinkedInquiries($lead);

        return $lead;
    }

    public function createManual(StaffUser $staff, array $data): Lead
    {
        $listing = isset($data['listing_id']) ? Listing::query()->find($data['listing_id']) : null;
        $buyer = User::query()->findOrFail($data['buyer_id']);

        $assignedTo = $data['assigned_to'] ?? null;
        if ($assignedTo === null && $this->isSales($staff)) {
            $assignedTo = $staff->id;
        }

        $lead = Lead::query()->create([
            'public_code' => $this->codes->next(PublicCodePrefix::Lead),
            'source' => $data['source'],
            'buyer_id' => $buyer->id,
            'seller_id' => $listing?->user_id,
            'listing_id' => $listing?->id,
            'assigned_to' => $assignedTo,
            'status' => LeadStatus::New,
            'priority' => $data['priority'] ?? LeadPriority::Medium->value,
            'next_action' => $data['next_action'] ?? 'first_call',
            'sla_due_at' => now()->addHours((int) config('aqdar.crm.sla_hours', 2)),
            'is_duplicate' => false,
        ]);

        $this->audit->write('lead.created', $staff, $lead, ['source' => $lead->source?->value]);
        if ($lead->assigned_to === null) {
            $this->autoAssign($lead);
        }

        return $lead;
    }

    public function paginateFor(StaffUser $staff, array $filters): LengthAwarePaginator
    {
        $query = Lead::query()
            ->with(['buyer', 'listing', 'assignee'])
            ->latest('id');

        $this->restrictToAssigned($query, $staff);

        $status = (string) ($filters['status'] ?? '');
        if ($status === 'overdue') {
            $query->overdueFollowUp();
        } elseif ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }
        if (($filters['source'] ?? '') !== '') {
            $query->where('source', $filters['source']);
        }
        if (in_array($filters['priority'] ?? '', array_column(LeadPriority::cases(), 'value'), true)) {
            $query->where('priority', $filters['priority']);
        }
        $assignedTo = (string) ($filters['assigned_to'] ?? '');
        if ($assignedTo === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($assignedTo !== '') {
            $query->where('assigned_to', $assignedTo);
        }
        if (! empty($filters['q'])) {
            $like = '%'.addcslashes((string) $filters['q'], '%_\\').'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('public_code', 'like', $like)
                    ->orWhereHas('buyer', fn ($buyer) => $buyer->where('name', 'like', $like)->orWhere('phone', 'like', $like));
            });
        }
        QueueQuery::apply($query, $filters, [
            'code' => 'public_code',
            'status' => 'status',
            'priority' => 'priority',
            'sla' => 'sla_due_at',
            'created' => 'created_at',
        ]);

        return $query->paginate(20)->withQueryString();
    }

    /** @return array<string, int> */
    public function countsFor(StaffUser $staff): array
    {
        $base = Lead::query();
        $this->restrictToAssigned($base, $staff);

        $counts = ['all' => (clone $base)->count()];
        foreach (LeadStatus::cases() as $status) {
            $counts[$status->value] = (clone $base)->where('status', $status->value)->count();
        }
        $counts['overdue'] = (clone $base)->overdueFollowUp()->count();

        return $counts;
    }

    public function assign(Lead $lead, StaffUser $actor, int $assigneeId, ?string $reason = null): Lead
    {
        $this->assertCanManageAll($actor);
        $this->assertReassignReason($lead->assigned_to, $assigneeId, $reason);

        $assignee = StaffUser::query()->findOrFail($assigneeId);
        if (! $assignee->hasPermission('manage_crm')) {
            throw ValidationException::withMessages([
                'assigned_to' => [__('aqdar.crm.assignee_must_be_sales')],
            ]);
        }

        $lead->forceFill(['assigned_to' => $assignee->id])->save();
        $this->syncLinkedInquiries($lead);
        $this->audit->write('lead.assigned', $actor, $lead, array_filter([
            'assignee_id' => $assignee->id,
            'reason' => $reason,
        ]));

        return $lead->fresh(['buyer', 'listing', 'assignee']) ?? $lead;
    }

    /**
     * Replacing an existing owner must be justified; first assignment needs no reason.
     */
    public function assertReassignReason(?int $currentAssigneeId, int $newAssigneeId, ?string $reason): void
    {
        if ($currentAssigneeId === null || $currentAssigneeId === $newAssigneeId) {
            return;
        }
        if (mb_strlen(trim((string) $reason)) < 3) {
            throw ValidationException::withMessages([
                'reason' => [__('aqdar.crm.reassign_reason_required')],
            ]);
        }
    }

    public function addActivity(Lead $lead, StaffUser $actor, array $data): LeadActivity
    {
        $this->assertCanAct($actor, $lead);

        $activity = $lead->activities()->create([
            'actor_id' => $actor->id,
            'type' => $data['type'],
            'contacted_party' => $data['contacted_party'] ?? null,
            'body' => $data['body'],
            'due_at' => $data['due_at'] ?? null,
        ]);

        $updates = ['last_contacted_at' => now()];
        if ($lead->status === LeadStatus::New) {
            $updates['status'] = LeadStatus::Contacted;
        }
        if (! empty($data['seller_visible_amount'])) {
            $updates['seller_visible_amount'] = $data['seller_visible_amount'];
        }
        if (! empty($data['next_action'])) {
            $updates['next_action'] = $data['next_action'];
        }
        $lead->forceFill($updates)->save();
        $this->syncLinkedInquiries($lead);

        $this->audit->write('lead.activity', $actor, $lead, ['type' => $data['type']]);

        return $activity;
    }

    public function markLost(Lead $lead, StaffUser $actor, string $reason): Lead
    {
        $this->assertCanAct($actor, $lead);
        $lead->forceFill([
            'status' => LeadStatus::Lost,
            'lost_reason' => $reason,
        ])->save();
        $this->syncLinkedInquiries($lead);
        $this->audit->write('lead.lost', $actor, $lead, ['reason' => $reason]);

        return $lead->fresh() ?? $lead;
    }

    public function convert(Lead $lead, StaffUser $actor, string $reason, bool $createCase = true): Lead
    {
        $this->assertCanAct($actor, $lead);
        $lead->forceFill([
            'status' => LeadStatus::Converted,
            'next_action' => 'open_case',
        ])->save();
        $this->syncLinkedInquiries($lead);
        if ($createCase) {
            app(CaseService::class)->openFromLead($lead, $actor, $reason);
        }
        $this->audit->write('lead.converted', $actor, $lead, ['reason' => $reason]);

        return $lead->fresh(['transferCases']) ?? $lead;
    }

    public function setStatus(Lead $lead, StaffUser $actor, LeadStatus $status): Lead
    {
        $this->assertCanAct($actor, $lead);
        $lead->forceFill(['status' => $status])->save();
        $this->syncLinkedInquiries($lead);
        $this->audit->write('lead.status', $actor, $lead, ['status' => $status->value]);

        return $lead->fresh() ?? $lead;
    }

    public function syncLinkedInquiries(Lead $lead): void
    {
        $inquiryStatus = match ($lead->status) {
            LeadStatus::New => $lead->assigned_to ? InquiryStatus::InReview : InquiryStatus::New,
            LeadStatus::Lost, LeadStatus::Converted => InquiryStatus::Closed,
            default => InquiryStatus::Replied,
        };

        $lead->inquiries()->update(['status' => $inquiryStatus->value]);
    }

    public function revealParty(Lead $lead, StaffUser $actor, string $party): string
    {
        $this->assertCanReveal($actor, $lead);

        $user = $party === 'seller' ? $lead->seller : $lead->buyer;
        abort_if($user === null || $user->phone === null, 404);

        $this->audit->access('lead.phone_revealed', $actor, $lead, [
            'party' => $party,
            'user_id' => $user->id,
        ]);

        return $user->phone;
    }

    /** @return Collection<int, StaffUser> */
    public function assignableStaff()
    {
        return StaffUser::query()
            ->where('is_active', true)
            ->whereHas('role.permissions', fn ($query) => $query->where('slug', 'manage_crm'))
            ->orderBy('name')
            ->get();
    }

    public function canManageAll(StaffUser $staff): bool
    {
        return $this->managesAll($staff);
    }

    public function assertCanAct(StaffUser $staff, Lead $lead): void
    {
        if ($this->managesAll($staff)) {
            return;
        }

        if ($this->isSales($staff) && (int) $lead->assigned_to === (int) $staff->id) {
            return;
        }

        $this->deny($staff, 'manage_crm');
    }

    public function assertCanReveal(StaffUser $staff, Lead $lead): void
    {
        $this->assertCanAct($staff, $lead);
    }

    public function assertCanManageAll(StaffUser $staff): void
    {
        if ($this->managesAll($staff)) {
            return;
        }

        $this->deny($staff, 'manage_crm');
    }

    private function autoAssign(Lead $lead): void
    {
        if ($lead->assigned_to !== null) {
            return;
        }

        $rule = $this->settings->stringValue(
            'lead_assignment_rule',
            (string) config('aqdar.crm.assignment_rule', 'manual'),
        );
        if ($rule !== 'round_robin') {
            return;
        }

        $sales = StaffUser::query()
            ->where('is_active', true)
            ->whereHas('role', fn (Builder $query) => $query->where('slug', StaffRole::Sales->value))
            ->orderBy('id')
            ->get();

        if ($sales->isEmpty()) {
            return;
        }

        $cursor = (int) $this->settings->stringValue('lead_assignment_cursor', '0');
        $next = $sales->first(fn (StaffUser $member) => (int) $member->id > $cursor) ?? $sales->first();

        $lead->forceFill(['assigned_to' => $next->id])->save();
        $this->settings->put('lead_assignment_cursor', (string) $next->id, 'crm');
        $this->audit->write('lead.assigned', null, $lead, [
            'assignee_id' => $next->id,
            'rule' => 'round_robin',
        ]);
    }

    private function restrictToAssigned(Builder $query, StaffUser $staff): void
    {
        if ($this->isSales($staff) && ! $this->managesAll($staff)) {
            $query->where('assigned_to', $staff->id);
        }
    }

    private function managesAll(StaffUser $staff): bool
    {
        return $staff->isSuperAdmin()
            || $staff->role?->slug === StaffRole::Operations->value;
    }

    private function isSales(StaffUser $staff): bool
    {
        return $staff->role?->slug === StaffRole::Sales->value;
    }

    private function deny(StaffUser $staff, string $permission): never
    {
        $this->audit->write('permission_denied', $staff, null, [
            'permission' => $permission,
            'path' => request()->path(),
        ]);

        abort(403, __('aqdar.http.forbidden'));
    }
}
