<?php

namespace App\Modules\Listing\Http\Resources\Concerns;

use App\Modules\Catalog\Models\Amenity;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Listing\Models\Favorite;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

trait MapsCatalog
{
    /** @return array{id: mixed, slug: mixed, name: mixed}|null */
    protected function named(?Model $model): ?array
    {
        if ($model === null) {
            return null;
        }

        $name = method_exists($model, 'localizedName')
            ? $model->localizedName()
            : ($model->name ?? null);

        return [
            'id' => $model->getKey(),
            'slug' => $model->slug ?? null,
            'name' => $name,
        ];
    }

    /** @param list<int>|null $ids */
    protected function namedAmenities(?array $ids): array
    {
        return $this->namedFromCatalog(Amenity::class, $ids);
    }

    /** @param list<int>|null $ids */
    protected function namedTags(?array $ids): array
    {
        return $this->namedFromCatalog(Tag::class, $ids);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<int>|null  $ids
     * @return list<array{id: mixed, slug: mixed, name: mixed}>
     */
    private function namedFromCatalog(string $modelClass, ?array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids ?? []),
            fn (int $id) => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        $catalog = once(fn () => [
            Amenity::class => Amenity::query()->where('is_active', true)->orderBy('sort_order')->get()->keyBy('id'),
            Tag::class => Tag::query()->where('is_active', true)->orderBy('sort_order')->get()->keyBy('id'),
        ]);

        return collect($ids)
            ->map(fn (int $id) => $catalog[$modelClass][$id] ?? null)
            ->filter()
            ->map(fn (Model $model) => $this->named($model))
            ->values()
            ->all();
    }

    /** @return list<int> */
    protected function favoriteListingIds(?Authenticatable $user): array
    {
        if ($user === null) {
            return [];
        }

        $userId = $user->getAuthIdentifier();

        return once(fn () => Favorite::query()
            ->where('user_id', $userId)
            ->pluck('listing_id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }
}
