<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\CaseStage;
use App\Core\Enums\CaseStatus;
use App\Modules\Crm\Database\Factories\TransferCaseFactory;
use App\Modules\Finance\Models\Commission;
use App\Modules\Listing\Models\Listing;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'public_code',
    'buyer_id',
    'seller_id',
    'listing_id',
    'lead_id',
    'buyer_request_id',
    'assigned_to',
    'internal_stage',
    'client_label',
    'status',
    'missing_documents_count',
    'requested_document_note',
    'last_transition_at',
])]
class TransferCase extends Model
{
    /** @use HasFactory<TransferCaseFactory> */
    use HasFactory;

    protected $table = 'cases';

    protected static function newFactory(): TransferCaseFactory
    {
        return TransferCaseFactory::new();
    }

    protected function casts(): array
    {
        return [
            'internal_stage' => CaseStage::class,
            'status' => CaseStatus::class,
            'missing_documents_count' => 'integer',
            'last_transition_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function buyerRequest(): BelongsTo
    {
        return $this->belongsTo(BuyerRequest::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'assigned_to');
    }

    public function history(): HasMany
    {
        return $this->hasMany(CaseHistory::class, 'case_id')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CaseDocument::class, 'case_id')->latest('id');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class, 'case_id');
    }

    public function stageBadgeTone(): string
    {
        return match ($this->internal_stage) {
            CaseStage::DeveloperApproval => 'gold',
            CaseStage::AlignParties => 'violet',
            CaseStage::Completed => 'ok',
            CaseStage::NewClient => 'info',
            default => '',
        };
    }

    public function lifecycleBadgeTone(): string
    {
        return match ($this->status) {
            CaseStatus::WaitingForInformation => 'warn',
            CaseStatus::OnHold => 'gold',
            CaseStatus::Cancelled, CaseStatus::Lost => 'grey',
            CaseStatus::Completed => 'ok',
            default => '',
        };
    }
}
