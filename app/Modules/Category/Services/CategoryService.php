<?php

namespace App\Modules\Category\Services;

use App\Core\Cache\CachedRepository;
use App\Core\Enums\CategoryKind;
use App\Core\Enums\DistressedChild;
use App\Core\Enums\MarketSegment;
use App\Core\Services\AbstractService;
use App\Modules\Category\Models\Category;
use App\Modules\Category\Repositories\CategoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CategoryService extends AbstractService
{
    public function __construct(
        CategoryRepository $repository,
        private CachedRepository $cache,
    ) {
        parent::__construct($repository);
    }

    /** @return Collection<int, Category> */
    public function listActive(): Collection
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->cache->remember('active', fn () => $this->repository->allActive()->toArray());

        return Category::hydrate($rows);
    }

    /** @return Collection<int, Category> */
    public function treeActive(): Collection
    {
        return $this->repository->treeActive();
    }

    /** @return Collection<int, Category> */
    public function allForAdmin(): Collection
    {
        return $this->repository->allOrdered();
    }

    public function findBySlug(string $slug): ?Category
    {
        /** @var array<string, mixed>|null $row */
        $row = $this->cache->remember(
            $this->slugCacheKey($slug),
            fn () => $this->repository->findBySlug($slug)?->toArray(),
        );

        return $row === null ? null : (new Category)->forceFill($row)->syncOriginal();
    }

    public function create(array $data): Model
    {
        $data = $this->normalizeNames($data);
        $this->assertCanCreate($data);

        /** @var Category $category */
        $category = parent::create($data);
        $this->assertDistressedInvariant();
        $this->invalidate($category->slug);

        return $category;
    }

    public function update(int|string $id, array $data): Model
    {
        /** @var Category|null $existing */
        $existing = $this->repository->find($id);

        abort_if($existing === null, 404);

        $data = $this->normalizeNames($data);
        $this->assertCanUpdate($existing, $data);

        /** @var Category $category */
        $category = parent::update($id, $data);
        $this->assertDistressedInvariant();
        $this->invalidate($existing->slug);

        if (isset($data['slug']) && $data['slug'] !== $existing->slug) {
            $this->invalidate($data['slug']);
        }

        return $category;
    }

    public function delete(int|string $id): bool
    {
        /** @var Category|null $category */
        $category = $this->repository->find($id);

        if ($category === null) {
            return false;
        }

        if ($category->isProtected()) {
            throw ValidationException::withMessages([
                'slug' => [__('aqdar.catalog.protected_category')],
            ]);
        }

        $this->update($id, ['is_active' => false]);

        return true;
    }

    /** @param array<string, mixed> $data */
    private function normalizeNames(array $data): array
    {
        if (isset($data['name_ar']) && is_string($data['name_ar'])) {
            $data['name'] = $data['name_ar'];
        } elseif (isset($data['name']) && is_string($data['name'])) {
            $data['name_ar'] = $data['name_ar'] ?? $data['name'];
            $data['name_en'] = $data['name_en'] ?? $data['name'];
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function assertCanCreate(array $data): void
    {
        $parent = isset($data['parent_id']) ? Category::query()->find($data['parent_id']) : null;

        if ($parent?->isDistressedRoot()) {
            $activeCount = $parent->children()->where('is_active', true)->count();

            if ($activeCount >= 2) {
                throw ValidationException::withMessages([
                    'parent_id' => [__('aqdar.catalog.distressed_must_have_two_children')],
                ]);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function assertCanUpdate(Category $existing, array $data): void
    {
        if ($existing->isProtected() && isset($data['slug']) && $data['slug'] !== $existing->slug) {
            throw ValidationException::withMessages([
                'slug' => [__('aqdar.catalog.protected_category')],
            ]);
        }

        $willBeActive = array_key_exists('is_active', $data)
            ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)
            : $existing->is_active;

        if ($existing->isProtected() && $existing->is_active && ! $willBeActive) {
            throw ValidationException::withMessages([
                'is_active' => [__('aqdar.catalog.distressed_must_have_two_children')],
            ]);
        }
    }

    private function assertDistressedInvariant(): void
    {
        $roots = Category::query()
            ->where('kind', CategoryKind::Segment)
            ->where('segment', MarketSegment::Distressed)
            ->get();

        foreach ($roots as $root) {
            $active = $root->children()->where('is_active', true)->get();

            if ($active->isEmpty()) {
                continue;
            }

            $codes = $active
                ->map(fn (Category $child) => $child->distressed_child?->value)
                ->filter()
                ->sort()
                ->values()
                ->all();

            if ($active->count() !== 2 || $codes !== [DistressedChild::CurrentDefault->value, DistressedChild::ExpectedDefault->value]) {
                throw ValidationException::withMessages([
                    'parent_id' => [__('aqdar.catalog.distressed_must_have_two_children')],
                ]);
            }
        }
    }

    protected function invalidate(?string $slug = null): void
    {
        $keys = ['active', 'tree'];

        if ($slug !== null) {
            $keys[] = $this->slugCacheKey($slug);
        }

        $this->cache->forgetMany($keys);
    }

    protected function slugCacheKey(string $slug): string
    {
        return 'slug:'.$slug;
    }
}
