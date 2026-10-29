<?php

namespace App\Modules\Category\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Category\Http\Requests\StoreCategoryRequest;
use App\Modules\Category\Http\Requests\UpdateCategoryRequest;
use App\Modules\Category\Http\Resources\CategoryResource;
use App\Modules\Category\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends BaseController
{
    public function __construct(private CategoryService $categoryService) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->categoryService->treeActive());
    }

    public function showBySlug(string $slug): CategoryResource
    {
        $category = $this->categoryService->findBySlug($slug);

        abort_if($category === null, 404);

        return new CategoryResource($category);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, int $category): CategoryResource
    {
        return new CategoryResource($this->categoryService->update($category, $request->validated()));
    }

    public function destroy(int $category): JsonResponse
    {
        $deleted = $this->categoryService->delete($category);

        abort_if(! $deleted, 404);

        return response()->json(null, 204);
    }
}
