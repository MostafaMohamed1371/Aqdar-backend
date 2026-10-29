<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Project;
use App\Modules\Catalog\Services\MasterDataService;
use App\Modules\Dashboard\Http\Requests\SaveCatalogRecordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DeveloperController extends BaseController
{
    public function __construct(private MasterDataService $masterData) {}

    public function index(): View
    {
        return view('dashboard::developers.index', [
            'developers' => Developer::query()->withCount('projects')->orderBy('is_other')->orderBy('sort_order')->get(),
            'projects' => Project::query()->with('developer')->orderBy('is_other')->orderBy('sort_order')->get(),
        ]);
    }

    public function storeDeveloper(SaveCatalogRecordRequest $request): RedirectResponse
    {
        $this->masterData->createDeveloper($request->validated());

        return redirect()->route('dashboard.developers.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function updateDeveloper(SaveCatalogRecordRequest $request, Developer $developer): RedirectResponse
    {
        $this->masterData->updateDeveloper($developer, $request->validated());

        return redirect()->route('dashboard.developers.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function destroyDeveloper(Developer $developer): RedirectResponse
    {
        $this->masterData->deleteDeveloper($developer);

        return redirect()->route('dashboard.developers.index')->with('status', __('aqdar.catalog.deactivated'));
    }

    public function storeProject(SaveCatalogRecordRequest $request): RedirectResponse
    {
        $this->masterData->createProject($request->validated());

        return redirect()->route('dashboard.developers.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function updateProject(SaveCatalogRecordRequest $request, Project $project): RedirectResponse
    {
        $this->masterData->updateProject($project, $request->validated());

        return redirect()->route('dashboard.developers.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function destroyProject(Project $project): RedirectResponse
    {
        $this->masterData->deleteProject($project);

        return redirect()->route('dashboard.developers.index')->with('status', __('aqdar.catalog.deactivated'));
    }
}
