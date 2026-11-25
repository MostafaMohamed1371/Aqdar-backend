<?php

namespace App\Modules\Crm\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\CaseDocumentKind;
use App\Core\Enums\CaseStage;
use App\Core\Enums\CaseStatus;
use App\Core\Enums\Permission;
use App\Core\Enums\PublicCodePrefix;
use App\Core\Identity\PublicCodeGenerator;
use App\Core\Services\QueueQuery;
use App\Core\Uploads\UploadScanner;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\CaseDocument;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\RequestMatch;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Crm\Services\Ocr\DocumentExtractionService;
use App\Modules\Dashboard\Support\DeskLiveRow;
use App\Modules\Dashboard\Support\DeskRealtime;
use App\Modules\Listing\Models\Listing;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CaseService
{
    public function __construct(
        private PublicCodeGenerator $codes,
        private AuditLogger $audit,
        private LeadService $leads,
        private NotificationService $notifications,
        private UploadScanner $uploads,
        private DocumentExtractionService $extractions,
    ) {}

    public function openFromLead(Lead $lead, StaffUser $actor, string $reason): TransferCase
    {
        $existing = $this->activeCase(
            TransferCase::query()->where('lead_id', $lead->id)->where('listing_id', $lead->listing_id)
        );
        if ($existing !== null) {
            return $existing;
        }

        return $this->open([
            'buyer_id' => $lead->buyer_id,
            'seller_id' => $lead->seller_id,
            'listing_id' => $lead->listing_id,
            'lead_id' => $lead->id,
            'assigned_to' => $lead->assigned_to ?? $actor->id,
        ], $actor, $reason);
    }

    /**
     * Each confirmed transaction gets its own case: one active case per (request, listing) pair.
     */
    public function openFromBuyerRequest(BuyerRequest $request, StaffUser $actor, string $reason, ?RequestMatch $match = null): TransferCase
    {
        $match ??= $request->matches()->whereNotNull('sent_at')->orderByDesc('score')->first();
        $listingId = $match?->listing_id;
        $listing = $listingId ? Listing::query()->find($listingId) : null;

        $existing = $this->activeCase(
            TransferCase::query()->where('buyer_request_id', $request->id)->where('listing_id', $listing?->id)
        );
        if ($existing !== null) {
            return $existing;
        }

        return $this->open([
            'buyer_id' => $request->user_id,
            'seller_id' => $listing?->user_id,
            'listing_id' => $listing?->id,
            'lead_id' => $request->lead_id,
            'buyer_request_id' => $request->id,
            'assigned_to' => $request->assigned_to ?? $actor->id,
        ], $actor, $reason);
    }

    public function createManual(StaffUser $staff, array $data): TransferCase
    {
        $listing = isset($data['listing_id']) ? Listing::query()->findOrFail($data['listing_id']) : null;

        return $this->open([
            'buyer_id' => $data['buyer_id'],
            'seller_id' => $listing?->user_id,
            'listing_id' => $listing?->id,
            'lead_id' => $data['lead_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? ($this->leads->canManageAll($staff) ? null : $staff->id),
        ], $staff, $data['reason'] ?? __('aqdar.cases.opened'));
    }

    private function activeCase(Builder $query): ?TransferCase
    {
        return $query
            ->whereNotIn('status', [CaseStatus::Cancelled->value, CaseStatus::Lost->value])
            ->latest('id')
            ->first();
    }

    /** @param array<string, mixed> $attrs */
    public function open(array $attrs, StaffUser $actor, string $reason): TransferCase
    {
        $stage = CaseStage::NewClient;
        $case = TransferCase::query()->create([
            'public_code' => $this->codes->next(PublicCodePrefix::Transfer),
            'buyer_id' => $attrs['buyer_id'],
            'seller_id' => $attrs['seller_id'] ?? null,
            'listing_id' => $attrs['listing_id'] ?? null,
            'lead_id' => $attrs['lead_id'] ?? null,
            'buyer_request_id' => $attrs['buyer_request_id'] ?? null,
            'assigned_to' => $attrs['assigned_to'] ?? $actor->id,
            'internal_stage' => $stage,
            'client_label' => $stage->clientLabel(),
            'status' => CaseStatus::Open,
            'missing_documents_count' => 0,
            'last_transition_at' => now(),
        ]);
        $this->recordHistory($case, null, $stage, $actor, $reason, null);
        $this->audit->write('case.opened', $actor, $case, ['reason' => $reason]);
        $this->notifyParties($case, __('aqdar.cases.client.'.$stage->clientLabel()), $stage->value);
        $case->loadMissing(['listing']);
        DeskRealtime::push('cases', 'opened', (int) $case->id, meta: [
            'public_code' => $case->public_code,
            'status' => CaseStatus::Open->value,
            'stage' => $stage->value,
            'row' => DeskLiveRow::transferCase($case),
        ]);

        return $case;
    }

    public function paginateFor(StaffUser $staff, array $filters): LengthAwarePaginator
    {
        $query = TransferCase::query()
            ->with(['buyer', 'seller', 'listing', 'assignee'])
            ->latest('id');
        $this->restrictToAssigned($query, $staff);

        $stage = (string) ($filters['stage'] ?? '');
        if ($stage !== '' && $stage !== 'all') {
            $query->where('internal_stage', $stage);
        }
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }
        if (($filters['assigned_to'] ?? '') !== '') {
            $query->where('assigned_to', $filters['assigned_to']);
        }
        if (! empty($filters['q'])) {
            $like = '%'.addcslashes((string) $filters['q'], '%_\\').'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('public_code', 'like', $like)
                    ->orWhereHas('buyer', fn ($buyer) => $buyer->where('name', 'like', $like));
            });
        }
        QueueQuery::apply($query, $filters, [
            'code' => 'public_code',
            'stage' => 'stage',
            'status' => 'status',
            'updated' => 'updated_at',
        ]);

        return $query->paginate(20)->withQueryString();
    }

    /** @return array<string, int> */
    public function countsFor(StaffUser $staff): array
    {
        $base = TransferCase::query();
        $this->restrictToAssigned($base, $staff);
        $counts = ['all' => (clone $base)->count()];
        foreach (CaseStage::cases() as $stage) {
            $counts[$stage->value] = (clone $base)->where('internal_stage', $stage->value)->count();
        }
        $counts['documents'] = (clone $base)->where('internal_stage', CaseStage::DocumentsComplete->value)->count();
        $counts['align'] = (clone $base)->where('internal_stage', CaseStage::AlignParties->value)->count();
        $counts['developer'] = (clone $base)->where('internal_stage', CaseStage::DeveloperApproval->value)->count();
        $counts['completed'] = (clone $base)->where('internal_stage', CaseStage::Completed->value)->count();

        return $counts;
    }

    public function showForStaff(StaffUser $staff, TransferCase $case): TransferCase
    {
        if (! $this->isFinanceOnly($staff)) {
            $this->assertCanAct($staff, $case);
        }

        return $case->load(['buyer', 'seller', 'listing.media', 'assignee', 'lead', 'history.actor', 'documents']);
    }

    public function showForBuyer(User $buyer, TransferCase $case): TransferCase
    {
        return $this->showForParty($buyer, $case);
    }

    public function showForParty(User $user, TransferCase $case): TransferCase
    {
        abort_unless($this->isParty($case, $user), 404);

        return $case->load(['listing.media', 'listing.financials', 'history', 'assignee']);
    }

    public function forBuyer(User $buyer): LengthAwarePaginator
    {
        return $this->forParty($buyer);
    }

    public function forParty(User $user): LengthAwarePaginator
    {
        return TransferCase::query()
            ->where(function (Builder $query) use ($user): void {
                $query->where('buyer_id', $user->id)
                    ->orWhere('seller_id', $user->id);
            })
            ->with(['listing.media', 'history'])
            ->latest('id')
            ->paginate(20);
    }

    public function assign(TransferCase $case, StaffUser $actor, int $assigneeId, ?string $reason = null): TransferCase
    {
        $this->leads->assertCanManageAll($actor);
        $this->leads->assertReassignReason($case->assigned_to, $assigneeId, $reason);
        $case->forceFill(['assigned_to' => $assigneeId])->save();
        $this->audit->write('case.assigned', $actor, $case, array_filter([
            'assignee_id' => $assigneeId,
            'reason' => $reason,
        ]));

        $fresh = $case->fresh(['assignee']) ?? $case;
        DeskRealtime::push('cases', 'assigned', (int) $fresh->id);

        return $fresh;
    }

    public function setStage(TransferCase $case, StaffUser $actor, CaseStage $stage, string $reason, ?string $note = null): TransferCase
    {
        $this->assertCanAdvance($actor, $case, $stage);
        $old = $case->internal_stage;
        $oldStatus = $case->status;
        $status = $stage === CaseStage::Completed ? CaseStatus::Completed : ($oldStatus ?? CaseStatus::Open);
        $case->forceFill([
            'internal_stage' => $stage,
            'client_label' => $stage->clientLabel(),
            'status' => $status,
            'last_transition_at' => now(),
        ])->save();
        $this->recordHistory($case, $old, $stage, $actor, $reason, $note);
        if ($oldStatus !== $status) {
            $this->recordLifecycle($case, $oldStatus, $status, $actor, $reason);
        }
        $this->audit->write('case.stage', $actor, $case, [
            'from' => $old?->value,
            'to' => $stage->value,
        ]);
        $this->notifyParties($case, __('aqdar.cases.client.'.$stage->clientLabel()), $stage->value);

        $fresh = $case->fresh(['history']) ?? $case;
        DeskRealtime::push('cases', 'stage', (int) $fresh->id, meta: [
            'stage' => $stage->value,
        ]);

        return $fresh;
    }

    public function setLifecycle(TransferCase $case, StaffUser $actor, CaseStatus $status, string $reason): TransferCase
    {
        $this->assertCanAdvance($actor, $case);
        $oldStatus = $case->status;
        $case->forceFill([
            'status' => $status,
            'last_transition_at' => now(),
        ])->save();
        $this->recordLifecycle($case, $oldStatus, $status, $actor, $reason);
        $this->audit->write('case.status', $actor, $case, [
            'from' => $oldStatus?->value,
            'to' => $status->value,
            'reason' => $reason,
        ]);
        $this->notifyParties($case, __('aqdar.cases.lifecycle.'.$status->value), 'lifecycle:'.$status->value);

        $fresh = $case->fresh(['history']) ?? $case;
        DeskRealtime::push('cases', 'status', (int) $fresh->id, meta: [
            'status' => $status->value,
        ]);

        return $fresh;
    }

    public function requestBuyerDocument(TransferCase $case, StaffUser $actor, string $note): TransferCase
    {
        $this->assertCanAct($actor, $case);
        $case->forceFill([
            'requested_document_note' => $note,
            'missing_documents_count' => max(1, (int) $case->missing_documents_count),
        ])->save();
        $this->audit->write('case.document_requested', $actor, $case, []);
        $fresh = $case->fresh(['buyer']) ?? $case;
        if ($fresh->buyer !== null) {
            $this->notifications->notifySafely($fresh->buyer, 'case_document_requested', [
                'public_code' => $fresh->public_code,
            ], 'case_document_requested:'.$fresh->id.':'.sha1((string) $note));
        }
        DeskRealtime::push('cases', 'document_requested', (int) $fresh->id, meta: [
            'public_code' => $fresh->public_code,
            'status' => $fresh->status?->value,
            'stage' => $fresh->internal_stage?->value,
        ]);

        return $fresh;
    }

    public function storeDocument(TransferCase $case, UploadedFile $file, CaseDocumentKind $kind, ?User $user, ?StaffUser $staff): CaseDocument
    {
        if ($user !== null) {
            abort_unless($this->isParty($case, $user), 404);
        }
        if ($staff !== null) {
            $this->assertCanAct($staff, $case);
        }

        $this->uploads->assertSafe($file, (array) config('aqdar.files.documents.mimes', []));

        $disk = (string) config('aqdar.files.documents.disk');
        $path = 'cases/'.$case->id.'/'.$kind->value.'-'.Str::random(8).'.'.$file->getClientOriginalExtension();
        Storage::disk($disk)->put($path, $file->getContent());

        $document = $case->documents()->create([
            'uploaded_by_user_id' => $user?->id,
            'uploaded_by_staff_id' => $staff?->id,
            'kind' => $kind,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        $this->extractions->queue($document);

        if ($kind === CaseDocumentKind::BuyerRequested && (int) $case->missing_documents_count > 0) {
            $case->forceFill([
                'missing_documents_count' => max(0, (int) $case->missing_documents_count - 1),
                'requested_document_note' => null,
            ])->save();
        }

        $actor = $staff ?? $user;
        if ($actor !== null) {
            $this->audit->write('case.document_uploaded', $actor, $document, [
                'case_id' => $case->id,
                'kind' => $kind->value,
            ]);
        }

        return $document;
    }

    public function canManageAll(StaffUser $staff): bool
    {
        return $this->leads->canManageAll($staff);
    }

    public function isFinanceOnly(StaffUser $staff): bool
    {
        return $staff->hasPermission(Permission::AdvanceFinanceStages)
            && ! $staff->hasPermission(Permission::AdvanceCase)
            && ! $this->leads->canManageAll($staff);
    }

    public function isReadyForFinance(TransferCase $case): bool
    {
        $current = $case->internal_stage;

        return $current === null
            || $current === CaseStage::DeveloperApproval
            || $current->isFinanceStage();
    }

    /** @return list<CaseStage> */
    public function allowedStagesFor(StaffUser $staff, TransferCase $case): array
    {
        if ($this->isFinanceOnly($staff)) {
            return array_values(array_filter(
                CaseStage::cases(),
                fn (CaseStage $stage): bool => $stage->isFinanceStage(),
            ));
        }

        return CaseStage::cases();
    }

    public function assignableStaff()
    {
        return $this->leads->assignableStaff();
    }

    public function assertCanAct(StaffUser $staff, TransferCase $case): void
    {
        $lead = $case->lead ?? new Lead(['assigned_to' => $case->assigned_to]);
        $this->leads->assertCanAct($staff, $lead);
    }

    private function assertCanAdvance(StaffUser $staff, TransferCase $case, ?CaseStage $target = null): void
    {
        if ($this->isFinanceOnly($staff)) {
            if ($target === null || ! $target->isFinanceStage() || ! $this->isReadyForFinance($case)) {
                $this->audit->write('permission_denied', $staff, $case, [
                    'permission' => Permission::AdvanceFinanceStages->value,
                    'path' => request()->path(),
                    'target' => $target?->value,
                ]);
                abort(403, __('aqdar.cases.finance_stage_denied'));
            }

            return;
        }

        $this->assertCanAct($staff, $case);
        if (! $staff->hasPermission(Permission::AdvanceCase)) {
            $this->leads->assertCanManageAll($staff);
        }
    }

    private function restrictToAssigned(Builder $query, StaffUser $staff): void
    {
        if (! $this->leads->canManageAll($staff) && $staff->role?->slug === 'sales') {
            $query->where('assigned_to', $staff->id);
        }
    }

    private function isParty(TransferCase $case, User $user): bool
    {
        return (int) $case->buyer_id === (int) $user->id
            || ($case->seller_id !== null && (int) $case->seller_id === (int) $user->id);
    }

    private function notifyParties(TransferCase $case, string $clientLabel, string $occurrence): void
    {
        $case->loadMissing(['buyer', 'seller']);
        $seen = [];
        foreach ([$case->buyer, $case->seller] as $user) {
            if ($user === null || isset($seen[$user->id])) {
                continue;
            }
            $seen[$user->id] = true;
            $this->notifications->notifySafely($user, 'case_stage_changed', [
                'public_code' => $case->public_code,
                'client_label' => $clientLabel,
            ], 'case_stage_changed:'.$case->id.':'.$occurrence.':'.$user->id);
        }
    }

    private function recordLifecycle(
        TransferCase $case,
        ?CaseStatus $old,
        CaseStatus $new,
        StaffUser $actor,
        string $reason,
    ): void {
        $case->history()->create([
            'actor_id' => $actor->id,
            'old_stage' => $case->internal_stage?->value,
            'new_stage' => $case->internal_stage?->value,
            'old_status' => $old?->value,
            'new_status' => $new->value,
            'reason' => $reason,
        ]);
    }

    private function recordHistory(
        TransferCase $case,
        ?CaseStage $old,
        CaseStage $new,
        StaffUser $actor,
        string $reason,
        ?string $note,
    ): void {
        $case->history()->create([
            'actor_id' => $actor->id,
            'old_stage' => $old?->value,
            'new_stage' => $new->value,
            'reason' => $reason,
            'internal_note' => $note,
        ]);
    }
}
