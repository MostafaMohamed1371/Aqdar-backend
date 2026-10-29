<?php

namespace App\Modules\Listing\Services;

use App\Core\Enums\DistressedChild;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\MarketSegment;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Listing\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListingSearchService
{
    /** @param array<string, mixed> $filters */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = $this->publishedQuery($filters);

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('listing_financials.declared_asking_price'),
            'price_desc' => $query->orderByDesc('listing_financials.declared_asking_price'),
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

    /** @return array{non_distressed: int, distressed: int, distressed_a: int, distressed_b: int} */
    public function counts(): array
    {
        $base = Listing::query()->whereIn('status', ListingStatus::publicCases());

        return [
            'non_distressed' => (clone $base)->where('segment', MarketSegment::NonDistressed)->count(),
            'distressed' => (clone $base)->where('segment', MarketSegment::Distressed)->count(),
            'distressed_a' => (clone $base)->where('distressed_child', DistressedChild::CurrentDefault)->count(),
            'distressed_b' => (clone $base)->where('distressed_child', DistressedChild::ExpectedDefault)->count(),
        ];
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
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }

        if (isset($filters['area_min'])) {
            $query->where('listings.area_sqm', '>=', $filters['area_min']);
        }

        if (isset($filters['area_max'])) {
            $query->where('listings.area_sqm', '<=', $filters['area_max']);
        }

        if (isset($filters['price_min'])) {
            $query->where('listing_financials.declared_asking_price', '>=', $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $query->where('listing_financials.declared_asking_price', '<=', $filters['price_max']);
        }

        if (isset($filters['down_payment_min'])) {
            $query->where('listing_financials.declared_remaining', '>=', $filters['down_payment_min']);
        }

        if (isset($filters['down_payment_max'])) {
            $query->where('listing_financials.declared_remaining', '<=', $filters['down_payment_max']);
        }

        if (isset($filters['installment_min'])) {
            $query->where('listing_financials.declared_installment_amount', '>=', $filters['installment_min']);
        }

        if (isset($filters['installment_max'])) {
            $query->where('listing_financials.declared_installment_amount', '<=', $filters['installment_max']);
        }

        $tagId = $this->resolveTagId($filters);

        if ($tagId !== null) {
            $query->whereJsonContains('listings.tag_ids', $tagId);
        }

        $term = isset($filters['q']) ? trim((string) $filters['q']) : '';

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('listings.title_ar', 'ilike', $like)
                    ->orWhere('listings.title_en', 'ilike', $like)
                    ->orWhere('listings.public_code', 'ilike', $like)
                    ->orWhereHas('area', function (Builder $area) use ($like): void {
                        $area->where('name_ar', 'ilike', $like)->orWhere('name_en', 'ilike', $like);
                    })
                    ->orWhereHas('unitType', function (Builder $type) use ($like): void {
                        $type->where('name_ar', 'ilike', $like)->orWhere('name_en', 'ilike', $like);
                    });
            });
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function resolveTagId(array $filters): ?int
    {
        if (! empty($filters['tag_id'])) {
            return (int) $filters['tag_id'];
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
