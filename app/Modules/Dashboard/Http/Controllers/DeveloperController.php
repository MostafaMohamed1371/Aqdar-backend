<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Catalog\Models\Developer;
use App\Modules\Catalog\Models\Project;
use App\Modules\Catalog\Services\MasterDataService;
use App\Modules\Dashboard\Http\Requests\SaveCatalogRecordRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeveloperController extends BaseController
{
    public function __construct(private MasterDataService $masterData) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'projects' ? 'projects' : 'developers';
        $search = trim((string) $request->query('q', ''));
        $pattern = '%'.addcslashes($search, '%_\\').'%';

        $developers = Developer::query()
            ->withCount('projects')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name_ar', 'like', $pattern)
                ->orWhere('name_en', 'like', $pattern)
                ->orWhere('commercial_register', 'like', $pattern)
                ->orWhere('contact_name', 'like', $pattern)
                ->orWhere('contact_email', 'like', $pattern)))
            ->orderBy('is_other')
            ->orderBy('sort_order')
            ->orderBy('id');

        $projects = Project::query()
            ->with('developer')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name_ar', 'like', $pattern)
                ->orWhere('name_en', 'like', $pattern)
                ->orWhereHas('developer', fn (Builder $developer) => $developer
                    ->where('name_ar', 'like', $pattern)
                    ->orWhere('name_en', 'like', $pattern))))
            ->orderBy('is_other')
            ->orderBy('sort_order')
            ->orderBy('id');

        return view('dashboard::developers.index', [
            'tab' => $tab,
            'search' => $search,
            'developers' => $tab === 'developers' ? $developers->paginate(20)->withQueryString() : null,
            'projects' => $tab === 'projects' ? $projects->paginate(20)->withQueryString() : null,
            'developerOptions' => Developer::query()->orderBy('is_other')->orderBy('sort_order')->get(),
        ]);
    }

    public function storeDeveloper(SaveCatalogRecordRequest $request): RedirectResponse
    {
        $this->masterData->createDeveloper($request->safe()->except(['logo', 'image']), $request->file('logo'));

        return redirect()->route('dashboard.developers.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function updateDeveloper(SaveCatalogRecordRequest $request, Developer $developer): RedirectResponse
    {
        $this->masterData->updateDeveloper($developer, $request->safe()->except(['logo', 'image']), $request->file('logo'));

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

        return redirect()->route('dashboard.developers.index', ['tab' => 'projects'])->with('status', __('aqdar.catalog.saved'));
    }

    public function updateProject(SaveCatalogRecordRequest $request, Project $project): RedirectResponse
    {
        $this->masterData->updateProject($project, $request->validated());

        return redirect()->route('dashboard.developers.index', ['tab' => 'projects'])->with('status', __('aqdar.catalog.saved'));
    }

    public function destroyProject(Project $project): RedirectResponse
    {
        $this->masterData->deleteProject($project);

        return redirect()->route('dashboard.developers.index', ['tab' => 'projects'])->with('status', __('aqdar.catalog.deactivated'));
    }
}
