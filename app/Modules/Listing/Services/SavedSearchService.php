<?php

namespace App\Modules\Listing\Services;

use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\SavedSearch;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class SavedSearchService
{
    public const MAX_PER_USER = 20;

    /** @var list<string> */
    private const FILTER_KEYS = [
        'q',
        'segment',
        'distressed_child',
        'governorate_id',
        'area_id',
        'developer_id',
        'project_id',
        'unit_type_id',
        'tag_id',
        'tag',
        'area_min',
        'area_max',
        'price_min',
        'price_max',
        'down_payment_min',
        'down_payment_max',
        'installment_min',
        'installment_max',
    ];

    public function __construct(
        private ListingSearchService $search,
        private NotificationService $notifications,
    ) {}

    public function forUser(User $user): Collection
    {
        return SavedSearch::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();
    }

    /** @param  array<string, mixed>  $data */
    public function create(User $user, array $data): SavedSearch
    {
        $count = SavedSearch::query()->where('user_id', $user->id)->count();
        if ($count >= self::MAX_PER_USER) {
            throw ValidationException::withMessages([
                'filters' => [__('aqdar.saved_searches.limit', ['max' => self::MAX_PER_USER])],
            ]);
        }

        return SavedSearch::query()->create([
            'user_id' => $user->id,
            'name' => isset($data['name']) && is_string($data['name']) && $data['name'] !== ''
                ? $data['name']
                : null,
            'filters' => $this->sanitizeFilters($data['filters'] ?? []),
            'notify' => array_key_exists('notify', $data) ? (bool) $data['notify'] : true,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $user, SavedSearch $savedSearch, array $data): SavedSearch
    {
        abort_unless((int) $savedSearch->user_id === (int) $user->id, 404);

        $payload = [];
        if (array_key_exists('name', $data)) {
            $payload['name'] = is_string($data['name']) && $data['name'] !== '' ? $data['name'] : null;
        }
        if (array_key_exists('notify', $data)) {
            $payload['notify'] = (bool) $data['notify'];
        }
        if (array_key_exists('filters', $data) && is_array($data['filters'])) {
            $payload['filters'] = $this->sanitizeFilters($data['filters']);
        }
        if ($payload !== []) {
            $savedSearch->fill($payload)->save();
        }

        return $savedSearch->fresh() ?? $savedSearch;
    }

    public function delete(User $user, SavedSearch $savedSearch): void
    {
        abort_unless((int) $savedSearch->user_id === (int) $user->id, 404);
        $savedSearch->delete();
    }

    public function matchListing(int $listingId): int
    {
        return $this->notifyMatches($listingId, 'smart_saved_search');
    }

    public function matchPriceDrop(int $listingId): int
    {
        $listing = Listing::query()->with('financials')->find($listingId);
        $price = $listing?->publicAskingPrice();
        if ($price === null) {
            return 0;
        }

        $priceText = (string) (int) (float) $price;
        $this->notifyFavorites($listing, $priceText);

        return $this->notifyMatches($listingId, 'smart_price_drop', [
            'price' => $priceText,
        ], ':price:'.$priceText);
    }

    /**
     * Buyers who saved the listing hear about a lower verified price even without a matching saved search.
     */
    private function notifyFavorites(Listing $listing, string $price): int
    {
        if (! $this->notifications->smartEnabled() || ! $listing->isPubliclyVisible()) {
            return 0;
        }

        $listing->loadMissing('area');
        $notified = 0;

        $listing->favorites()
            ->where('user_id', '!=', $listing->user_id)
            ->with('user')
            ->each(function ($favorite) use ($listing, $price, &$notified): void {
                $user = $favorite->user;
                if ($user === null || $user->isMarketplaceBlocked()) {
                    return;
                }

                $this->notifications->notifySafely($user, 'smart_favorite_updated', [
                    'listing_code' => $listing->public_code,
                    'listing_id' => $listing->id,
                    'area' => $listing->area?->localizedName(),
                    'price' => $price,
                ], 'smart_favorite_updated:listing:'.$listing->id.':user:'.$user->id.':price:'.$price);
                $notified++;
            });

        return $notified;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function notifyMatches(int $listingId, string $event, array $extra = [], string $idempotencySuffix = ''): int
    {
        if (! $this->notifications->smartEnabled()) {
            return 0;
        }

        $listing = Listing::query()->with(['area', 'unitType', 'financials', 'owner'])->find($listingId);
        if ($listing === null || ! $listing->status?->isPublic()) {
            return 0;
        }

        $matched = 0;
        SavedSearch::query()
            ->where('notify', true)
            ->with('user')
            ->each(function (SavedSearch $savedSearch) use ($listing, $event, $extra, $idempotencySuffix, &$matched): void {
                $owner = $savedSearch->user;
                if ($owner === null || $owner->isMarketplaceBlocked()) {
                    return;
                }
                if ((int) $listing->user_id === (int) $savedSearch->user_id) {
                    return;
                }
                if (! $this->search->matches($listing, $savedSearch->filters ?? [])) {
                    return;
                }

                $this->notifications->notifySafely($owner, $event, array_merge([
                    'listing_code' => $listing->public_code,
                    'listing_id' => $listing->id,
                    'area' => $listing->area?->localizedName(),
                    'unit_type' => $listing->unitType?->localizedName(),
                ], $extra), $event.':saved_search:'.$savedSearch->id.':listing:'.$listing->id.$idempotencySuffix);

                $savedSearch->forceFill(['last_matched_at' => now()])->save();
                $matched++;
            });

        return $matched;
    }

    /** @param  array<string, mixed>  $filters */
    private function sanitizeFilters(array $filters): array
    {
        $out = [];
        foreach (self::FILTER_KEYS as $key) {
            if (! array_key_exists($key, $filters) || $filters[$key] === null || $filters[$key] === '') {
                continue;
            }
            $out[$key] = $filters[$key];
        }

        return $out;
    }
}
