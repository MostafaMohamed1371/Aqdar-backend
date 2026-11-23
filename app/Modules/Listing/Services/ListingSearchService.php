<?php

namespace App\Modules\Listing\Services;

use App\Core\Enums\DistressedChild;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\MarketSegment;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Listing\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListingSearchService
{
    /** Half of the 0.001° step that map pins are rounded to. */
    private const PIN_HALF_STEP = 0.0005;

    /** @param array<string, mixed> $filters */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = $this->publishedQuery($filters);

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('listing_financials.public_asking_price'),
            'price_desc' => $query->orderByDesc('listing_financials.public_asking_price'),
            'area_asc' => $query->orderBy('listings.area_sqm'),
            'area_desc' => $query->orderByDesc('listings.area_sqm'),
            default => $query->orderByDesc('listings.published_at'),
        };

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

        return $query->paginate($perPage);
    }

    /** @param array<string, mixed> $filters */
    public function mapPins(array $filters): Collection
    {
        return $this->publishedQuery($filters)
            ->whereNotNull('listings.lat')
            ->whereNotNull('listings.lng')
            ->orderByDesc('listings.published_at')
            ->limit(200)
            ->get();
    }

    /** @return array{non_distressed: int, distressed: int, distressed_a: int, distressed_b: int, unit_types: list<array{id: int, slug: string|null, count: int}>, tags: list<array{id: int, slug: string|null, count: int}>} */
    public function counts(): array
    {
        $base = Listing::query()->whereIn('status', ListingStatus::publicCases());

        $unitTypes = UnitType::query()
            ->whereHas('listings', fn ($query) => $query->whereIn('status', ListingStatus::publicCases()))
            ->withCount(['listings' => fn ($query) => $query->whereIn('status', ListingStatus::publicCases())])
            ->orderBy('id')
            ->get(['id', 'slug'])
            ->map(fn (UnitType $type) => ['id' => $type->id, 'slug' => $type->slug, 'count' => (int) $type->listings_count])
            ->values()
            ->all();

        return [
            'non_distressed' => (clone $base)->where('segment', MarketSegment::NonDistressed)->count(),
            'distressed' => (clone $base)->where('segment', MarketSegment::Distressed)->count(),
            'distressed_a' => (clone $base)->where('distressed_child', DistressedChild::CurrentDefault)->count(),
            'distressed_b' => (clone $base)->where('distressed_child', DistressedChild::ExpectedDefault)->count(),
            'unit_types' => $unitTypes,
            'tags' => Tag::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'slug'])
                ->map(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'slug' => $tag->slug,
                    'count' => (clone $base)->whereJsonContains('tag_ids', $tag->id)->count(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Listing>
     */
    public function compare(array $ids): Collection
    {
        return Listing::query()
            ->with(['financials', 'governorate', 'area', 'developer', 'project', 'unitType', 'category', 'media'])
            ->whereIn('id', $ids)
            ->whereIn('status', ListingStatus::publicCases())
            ->get()
            ->sortBy(fn (Listing $listing) => array_search($listing->id, $ids, true))
            ->values();
    }

    /** @param array<string, mixed> $filters */
    public function matches(Listing $listing, array $filters): bool
    {
        if (! $listing->status?->isPublic()) {
            return false;
        }

        return $this->publishedQuery($filters)->where('listings.id', $listing->id)->exists();
    }

    /** @param array<string, mixed> $filters */
    private function publishedQuery(array $filters): Builder
    {
        $query = Listing::query()
            ->select('listings.*')
            ->leftJoin('listing_financials', 'listing_financials.listing_id', '=', 'listings.id')
            ->with(['financials', 'governorate', 'area', 'developer', 'project', 'unitType', 'category', 'media'])
            ->whereIn('listings.status', ListingStatus::publicCases());

        foreach ([
            'segment' => 'listings.segment',
            'distressed_child' => 'listings.distressed_child',
            'governorate_id' => 'listings.governorate_id',
            'area_id' => 'listings.area_id',
            'developer_id' => 'listings.developer_id',
            'project_id' => 'listings.project_id',
            'unit_type_id' => 'listings.unit_type_id',
            'category_id' => 'listings.category_id',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }

        if (empty($filters['unit_type_id']) && ! empty($filters['property_type'])) {
            $query->where('listings.unit_type_id', $this->resolveUnitTypeId((string) $filters['property_type']));
        }

        $box = isset($filters['bbox']) ? self::parseBoundingBox((string) $filters['bbox']) : null;

        if ($box !== null) {
            [$minLng, $minLat, $maxLng, $maxLat] = $box;

            $query->whereNotNull('listings.lat')
                ->whereNotNull('listings.lng')
                ->whereBetween('listings.lat', [$minLat - self::PIN_HALF_STEP, $maxLat + self::PIN_HALF_STEP])
                ->whereBetween('listings.lng', [$minLng - self::PIN_HALF_STEP, $maxLng + self::PIN_HALF_STEP]);
        }

        if (isset($filters['bedrooms']) && $filters['bedrooms'] !== '') {
            $query->where('listings.bedrooms', (int) $filters['bedrooms']);
        }

        if (! empty($filters['finishing'])) {
            $query->where('listings.finishing', 'like', addcslashes(trim((string) $filters['finishing']), '%_\\'));
        }

        if (! empty($filters['neighborhood'])) {
            $query->where('listings.neighborhood', 'like', addcslashes(trim((string) $filters['neighborhood']), '%_\\'));
        }

        if (isset($filters['area_min'])) {
            $query->where('listings.area_sqm', '>=', $filters['area_min']);
        }

        if (isset($filters['area_max'])) {
            $query->where('listings.area_sqm', '<=', $filters['area_max']);
        }

        if (isset($filters['price_min'])) {
            $query->where('listing_financials.public_asking_price', '>=', $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $query->where('listing_financials.public_asking_price', '<=', $filters['price_max']);
        }

        if (isset($filters['down_payment_min'])) {
            $query->where('listing_financials.public_remaining', '>=', $filters['down_payment_min']);
        }

        if (isset($filters['down_payment_max'])) {
            $query->where('listing_financials.public_remaining', '<=', $filters['down_payment_max']);
        }

        if (isset($filters['installment_min'])) {
            $query->where('listing_financials.public_installment_amount', '>=', $filters['installment_min']);
        }

        if (isset($filters['installment_max'])) {
            $query->where('listing_financials.public_installment_amount', '<=', $filters['installment_max']);
        }

        $tagId = $this->resolveTagId($filters);

        if ($tagId !== null) {
            $query->whereJsonContains('listings.tag_ids', $tagId);
        }

        $term = isset($filters['q']) ? trim((string) $filters['q']) : '';

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('listings.title_ar', 'like', $like)
                    ->orWhere('listings.title_en', 'like', $like)
                    ->orWhere('listings.public_code', 'like', $like)
                    ->orWhereHas('area', function (Builder $area) use ($like): void {
                        $area->where('name_ar', 'like', $like)->orWhere('name_en', 'like', $like);
                    })
                    ->orWhereHas('unitType', function (Builder $type) use ($like): void {
                        $type->where('name_ar', 'like', $like)->orWhere('name_en', 'like', $like);
                    });
            });
        }

        return $query;
    }

    /**
     * Parses `min_lng,min_lat,max_lng,max_lat`. Matching uses coordinates rounded to 3 decimals, like map pins, so a box can never narrow down an exact location.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public static function parseBoundingBox(string $bbox): ?array
    {
        $parts = array_map('trim', explode(',', $bbox));

        if (count($parts) !== 4 || count(array_filter($parts, 'is_numeric')) !== 4) {
            return null;
        }

        [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', $parts);

        $valid = $minLng >= -180 && $maxLng <= 180 && $minLat >= -90 && $maxLat <= 90
            && $minLng <= $maxLng && $minLat <= $maxLat;

        return $valid ? [$minLng, $minLat, $maxLng, $maxLat] : null;
    }

    private function resolveUnitTypeId(string $propertyType): int
    {
        $propertyType = trim($propertyType);

        if (ctype_digit($propertyType)) {
            return (int) $propertyType;
        }

        return (int) UnitType::query()->where('slug', $propertyType)->where('is_active', true)->value('id');
    }

    /** @param array<string, mixed> $filters */
    private function resolveTagId(array $filters): ?int
    {
        if (! empty($filters['tag_id'])) {
            return (int) $filters['tag_id'];
        }

        if (empty($filters['tag']) && ! empty($filters['featured'])) {
            $filters['tag'] = 'featured';
        }

        if (empty($filters['tag'])) {
            return null;
        }

        $id = Tag::query()
            ->where('slug', $filters['tag'])
            ->where('is_active', true)
            ->value('id');

        return $id !== null ? (int) $id : 0;
    }
}
