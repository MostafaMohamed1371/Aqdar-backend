<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\BuyerIntent;
use App\Core\Enums\BuyerRequestStatus;
use App\Core\Enums\ContactMethod;
use App\Core\Enums\SegmentPreference;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Catalog\Models\Project;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Crm\Database\Factories\BuyerRequestFactory;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'public_code',
    'user_id',
    'lead_id',
    'assigned_to',
    'status',
    'is_duplicate',
    'duplicate_of_id',
    'intent',
    'segment_preference',
    'unit_type_id',
    'governorate_id',
    'area_id',
    'developer_id',
    'project_id',
    'area_sqm_min',
    'area_sqm_max',
    'bedrooms',
    'finishing',
    'budget_min',
    'budget_max',
    'down_payment',
    'monthly_installment',
    'duration_years',
    'timeline',
    'priorities',
    'contact_method',
    'consent_at',
    'criteria',
])]
class BuyerRequest extends Model
{
    /** @use HasFactory<BuyerRequestFactory> */
    use HasFactory;

    protected static function newFactory(): BuyerRequestFactory
    {
        return BuyerRequestFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => BuyerRequestStatus::class,
            'intent' => BuyerIntent::class,
            'segment_preference' => SegmentPreference::class,
            'contact_method' => ContactMethod::class,
            'consent_at' => 'datetime',
            'criteria' => 'array',
            'is_duplicate' => 'boolean',
            'area_sqm_min' => 'decimal:2',
            'area_sqm_max' => 'decimal:2',
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'monthly_installment' => 'decimal:2',
            'bedrooms' => 'integer',
            'duration_years' => 'integer',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'assigned_to');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(RequestMatch::class)->orderByDesc('score');
    }

    public function history(): HasMany
    {
        return $this->hasMany(BuyerRequestHistory::class)->orderBy('id');
    }

    public function transferCases(): HasMany
    {
        return $this->hasMany(TransferCase::class);
    }

    public function statusBadgeTone(): string
    {
        return match ($this->status) {
            BuyerRequestStatus::New => 'warn',
            BuyerRequestStatus::OptionsShared, BuyerRequestStatus::NegotiationVisit => 'gold',
            BuyerRequestStatus::Converted, BuyerRequestStatus::ClosedNoMatch => 'ok',
            default => '',
        };
    }

    public function budgetLabel(): string
    {
        if ($this->budget_max !== null) {
            return number_format((float) $this->budget_max);
        }
        if ($this->budget_min !== null) {
            return number_format((float) $this->budget_min);
        }

        return '—';
    }

    public function areaRangeLabel(): string
    {
        if ($this->area_sqm_min === null && $this->area_sqm_max === null) {
            return '—';
        }

        return trim(($this->area_sqm_min !== null ? (string) (int) $this->area_sqm_min : '')
            .' - '
            .($this->area_sqm_max !== null ? (string) (int) $this->area_sqm_max : ''));
    }
}
