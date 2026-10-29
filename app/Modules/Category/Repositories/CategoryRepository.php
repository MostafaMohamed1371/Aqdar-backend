<?php

namespace App\Modules\Category\Repositories;

use App\Core\Repositories\AbstractRepository;
use App\Modules\Category\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository extends AbstractRepository
{
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }

    /** @return Collection<int, Category> */
    public function allActive(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Category> */
    public function treeActive(): Collection
    {
        return $this->model->newQuery()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Category> */
    public function allOrdered(): Collection
    {
        return $this->model->newQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->model->newQuery()->where('slug', $slug)->first();
    }
}
