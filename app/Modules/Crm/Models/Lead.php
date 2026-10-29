<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\LeadPriority;
use App\Core\Enums\LeadSource;
use App\Core\Enums\LeadStatus;
use App\Modules\Crm\Database\Factories\LeadFactory;
use App\Modules\Listing\Models\Listing;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'public_code',
    'source',
    'buyer_id',
    'seller_id',
    'listing_id',
    'assigned_to',
    'status',
    'priority',
    'next_action',
    'next_action_at',
    'sla_due_at',
    'last_contacted_at',
    'lost_reason',
    'is_duplicate',
    'duplicate_of_id',
    'seller_visible_amount',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    protected static function newFactory(): LeadFactory
    {
        return LeadFactory::new();
    }

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'priority' => LeadPriority::class,
            'next_action_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'is_duplicate' => 'boolean',
            'seller_visible_amount' => 'decimal:2',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'assigned_to');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function buyerRequests(): HasMany
    {
        return $this->hasMany(BuyerRequest::class);
    }

    public function transferCases(): HasMany
    {
        return $this->hasMany(TransferCase::class);
    }

    public function slaBreached(): bool
    {
        return $this->status?->isOpen()
            && $this->sla_due_at !== null
            && $this->sla_due_at->isPast()
            && in_array($this->status, [LeadStatus::New], true);
    }

    public function slaOverdueHours(): ?int
    {
        if (! $this->slaBreached() || $this->sla_due_at === null) {
            return null;
        }

        return max(1, (int) $this->sla_due_at->diffInHours(now()));
    }

    public function statusBadgeTone(): string
    {
        return match ($this->status) {
            LeadStatus::New => 'info',
            LeadStatus::Qualified, LeadStatus::Converted => 'ok',
            LeadStatus::VisitScheduled, LeadStatus::OfferSent => 'gold',
            LeadStatus::Lost => 'grey',
            default => '',
        };
    }

    public function priorityBadgeTone(): string
    {
        return match ($this->priority) {
            LeadPriority::High => 'bad',
            LeadPriority::Medium => 'gold',
            default => '',
        };
    }
}
