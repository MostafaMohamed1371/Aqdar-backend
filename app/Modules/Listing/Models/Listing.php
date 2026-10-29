<?php

namespace App\Modules\Listing\Models;

use App\Core\Enums\DistressedChild;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\MarketSegment;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Catalog\Models\Project;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Category\Models\Category;
use App\Modules\Listing\Database\Factories\ListingFactory;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'public_code',
    'user_id',
    'assigned_reviewer_id',
    'status',
    'category_id',
    'segment',
    'distressed_child',
    'governorate_id',
    'area_id',
    'developer_id',
    'project_id',
    'unit_type_id',
    'title_ar',
    'title_en',
    'description_ar',
    'description_en',
    'area_sqm',
    'bedrooms',
    'bathrooms',
    'floor',
    'finishing',
    'furnished',
    'handover_date',
    'neighborhood',
    'phase',
    'unit_code',
    'parking',
    'lat',
    'lng',
    'amenity_ids',
    'tag_ids',
    'media_meta',
    'documents_meta',
    'contact_flagged',
    'contact_flag_reason',
    'consent_at',
    'submitted_at',
    'published_at',
    'offer_expires_at',
    'version',
    'view_count',
    'review_reference',
    'commission_policy_code',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    protected static function newFactory(): ListingFactory
    {
        return ListingFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'segment' => MarketSegment::class,
            'distressed_child' => DistressedChild::class,
            'area_sqm' => 'decimal:2',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'floor' => 'integer',
            'handover_date' => 'date',
            'parking' => 'integer',
            'offer_expires_at' => 'datetime',
            'view_count' => 'integer',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'amenity_ids' => 'array',
            'tag_ids' => 'array',
            'media_meta' => 'array',
            'documents_meta' => 'array',
            'contact_flagged' => 'boolean',
            'consent_at' => 'datetime',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedReviewer(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'assigned_reviewer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    public function financials(): HasOne
    {
        return $this->hasOne(ListingFinancial::class);
    }

    public function installmentItems(): HasMany
    {
        return $this->hasMany(InstallmentItem::class)->orderBy('sequence');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ListingStatusHistory::class)->orderBy('id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ListingDocument::class)->orderByDesc('version');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ListingMedia::class)->orderBy('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->status === ListingStatus::Published;
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status?->isPublic() === true;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [ListingStatus::Draft, ListingStatus::NeedsChanges], true)
            || $this->isPubliclyVisible();
    }

    public function fieldSnapshots(): HasMany
    {
        return $this->hasMany(ListingFieldSnapshot::class)->orderByDesc('version');
    }

    public function previousSnapshot(): ?ListingFieldSnapshot
    {
        $this->loadMissing('fieldSnapshots');

        $version = (int) $this->version;
        $older = $this->fieldSnapshots->first(fn ($row) => (int) $row->version < $version);

        if ($older !== null) {
            return $older;
        }

        return $this->fieldSnapshots->first(fn ($row) => (int) $row->version === $version);
    }

    /** @return array{id: int, public_code: string|null, title: string, photo: string|null}|null */
    public function publicCard(): ?array
    {
        $cover = $this->media->first(fn ($media) => $media->isApproved());

        return [
            'id' => $this->id,
            'public_code' => $this->public_code,
            'title' => $this->localizedTitle(),
            'photo' => $cover?->publicUrl(),
        ];
    }

    public function localizedTitle(): string
    {
        if (app()->getLocale() === 'en') {
            return $this->title_en ?: (string) $this->title_ar;
        }

        return $this->title_ar ?: (string) $this->title_en;
    }

    public function deskSummary(): string
    {
        $this->loadMissing(['unitType', 'area']);
        $unit = trim((string) $this->unitType?->localizedName());
        $area = trim((string) $this->area?->localizedName());
        if ($unit !== '' && $area !== '') {
            return $unit.' · '.$area;
        }

        return $this->localizedTitle() ?: (string) $this->public_code;
    }

    public function localizedDescription(): ?string
    {
        if (app()->getLocale() === 'en') {
            return $this->description_en ?: $this->description_ar;
        }

        return $this->description_ar ?: $this->description_en;
    }

    public function publicAskingPrice(): ?string
    {
        $financials = $this->financials;

        if ($financials === null) {
            return null;
        }

        if ($financials->asking_price_verified && $financials->verified_asking_price !== null) {
            return (string) $financials->verified_asking_price;
        }

        return $financials->declared_asking_price !== null
            ? (string) $financials->declared_asking_price
            : null;
    }

    public function askingPriceSource(): string
    {
        $financials = $this->financials;

        if ($financials?->asking_price_verified && $financials->verified_asking_price !== null) {
            return 'verified';
        }

        return 'declared';
    }

    public function statusBadgeTone(): string
    {
        return match ($this->status) {
            ListingStatus::Published, ListingStatus::Approved => 'ok',
            ListingStatus::Rejected, ListingStatus::Hold, ListingStatus::Closed, ListingStatus::Archived => 'bad',
            ListingStatus::InReview, ListingStatus::NeedsChanges, ListingStatus::Submitted, ListingStatus::Resubmitted, ListingStatus::UnderOffer => 'warn',
            default => 'grey',
        };
    }

    public function marketLabel(): string
    {
        if ($this->segment === MarketSegment::Distressed) {
            $suffix = $this->distressed_child?->value;

            return __('aqdar.listings.desk.distressed').($suffix ? ' · '.strtoupper($suffix) : '');
        }

        return __('aqdar.listings.desk.non_distressed');
    }

    /** @param Builder<Listing> $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ListingStatus::Published);
    }

    /** @param Builder<Listing> $query */
    public function scopePublic(Builder $query): Builder
    {
        return $query->whereIn('status', ListingStatus::publicCases());
    }
}
