<?php

namespace App\Modules\Category\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Category\Models\Category;
use App\Modules\Listing\Http\Resources\ListingFormFieldResource;
use App\Modules\Listing\Services\DynamicFormService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryFormApiController extends BaseController
{
    public function __construct(private DynamicFormService $forms) {}

    public function show(int $category): AnonymousResourceCollection
    {
        $model = Category::query()->where('is_active', true)->findOrFail($category);

        return ListingFormFieldResource::collection($this->forms->fieldsFor($model))
            ->additional(['form_version' => (int) $model->form_version]);
    }
}
