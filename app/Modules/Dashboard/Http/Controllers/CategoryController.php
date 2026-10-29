<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Category\Http\Requests\StoreCategoryRequest;
use App\Modules\Category\Http\Requests\UpdateCategoryRequest;
use App\Modules\Category\Models\Category;
use App\Modules\Category\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends BaseController
{
    public function __construct(private CategoryService $categoryService) {}

    public function index(): View
    {
        return view('dashboard::categories.index', [
            'categories' => $this->categoryService->allForAdmin(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard::categories.create', [
            'parentCategories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create($request->validated());

        return redirect()
            ->route('dashboard.categories.index')
            ->with('status', __('structer.category_created'));
    }

    public function edit(Category $category): View
    {
        return view('dashboard::categories.edit', [
            'category' => $category,
            'parentCategories' => Category::query()
                ->where('id', '!=', $category->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->categoryService->update($category->id, $request->validated());

        return redirect()
            ->route('dashboard.categories.index')
            ->with('status', __('structer.category_updated'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->categoryService->delete($category->id);

        return redirect()
            ->route('dashboard.categories.index')
            ->with('status', __('aqdar.catalog.deactivated'));
    }
}
