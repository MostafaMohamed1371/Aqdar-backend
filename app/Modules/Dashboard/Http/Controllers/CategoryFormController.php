<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Enums\FormFieldType;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Category\Models\Category;
use App\Modules\Dashboard\Http\Requests\SaveCategoryFormRequest;
use App\Modules\Listing\Services\DynamicFormService;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryFormController extends BaseController
{
    public function __construct(private DynamicFormService $forms) {}

    public function edit(Category $category): View
    {
        return view('dashboard::categories.form', [
            'category' => $category,
            'fields' => $this->forms->fieldsFor($category),
            'types' => FormFieldType::cases(),
        ]);
    }

    public function update(SaveCategoryFormRequest $request, Category $category): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $version = $this->forms->publish($category, $request->rows(), $staff);

        return redirect()
            ->route('dashboard.categories.form.edit', $category)
            ->with('status', __('aqdar.forms.published', ['version' => $version]));
    }
}
