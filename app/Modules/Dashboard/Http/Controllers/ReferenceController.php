<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Catalog\Models\Amenity;
use App\Modules\Catalog\Models\Area;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Catalog\Models\UnitType;
use App\Modules\Catalog\Services\MasterDataService;
use App\Modules\Dashboard\Http\Requests\SaveCatalogRecordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReferenceController extends BaseController
{
    public function __construct(private MasterDataService $masterData) {}

    public function locations(): View
    {
        return view('dashboard::reference.locations', [
            'governorates' => Governorate::query()->with('areas')->orderBy('sort_order')->get(),
        ]);
    }

    public function storeGovernorate(SaveCatalogRecordRequest $request): RedirectResponse
    {
        $this->masterData->createGovernorate($request->validated());

        return redirect()->route('dashboard.locations.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function updateGovernorate(SaveCatalogRecordRequest $request, Governorate $governorate): RedirectResponse
    {
        $this->masterData->updateGovernorate($governorate, $request->safe()->except(['image']));

        return redirect()->route('dashboard.locations.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function destroyGovernorate(Governorate $governorate): RedirectResponse
    {
        $this->masterData->deleteGovernorate($governorate);

        return redirect()->route('dashboard.locations.index')->with('status', __('aqdar.catalog.deactivated'));
    }

    public function storeArea(SaveCatalogRecordRequest $request): RedirectResponse
    {
        $this->masterData->createArea($request->validated());

        return redirect()->route('dashboard.locations.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function updateArea(SaveCatalogRecordRequest $request, Area $area): RedirectResponse
    {
        $this->masterData->updateArea($area, $request->safe()->except(['image']));

        return redirect()->route('dashboard.locations.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function destroyArea(Area $area): RedirectResponse
    {
        $this->masterData->deleteArea($area);

        return redirect()->route('dashboard.locations.index')->with('status', __('aqdar.catalog.deactivated'));
    }

    public function unitTypes(): View
    {
        return view('dashboard::reference.unit-types', [
            'unitTypes' => UnitType::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function storeUnitType(SaveCatalogRecordRequest $request): RedirectResponse
    {
        $this->masterData->createUnitType($request->validated());

        return redirect()->route('dashboard.unit-types.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function updateUnitType(SaveCatalogRecordRequest $request, UnitType $unitType): RedirectResponse
    {
        $this->masterData->updateUnitType($unitType, $request->safe()->except(['image']));

        return redirect()->route('dashboard.unit-types.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function destroyUnitType(UnitType $unitType): RedirectResponse
    {
        $this->masterData->deleteUnitType($unitType);

        return redirect()->route('dashboard.unit-types.index')->with('status', __('aqdar.catalog.deactivated'));
    }

    public function amenities(): View
    {
        return view('dashboard::reference.amenities', [
            'amenities' => Amenity::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function storeAmenity(SaveCatalogRecordRequest $request): RedirectResponse
    {
        $this->masterData->createAmenity($request->validated());

        return redirect()->route('dashboard.amenities.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function updateAmenity(SaveCatalogRecordRequest $request, Amenity $amenity): RedirectResponse
    {
        $this->masterData->updateAmenity($amenity, $request->safe()->except(['image']));

        return redirect()->route('dashboard.amenities.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function destroyAmenity(Amenity $amenity): RedirectResponse
    {
        $this->masterData->deleteAmenity($amenity);

        return redirect()->route('dashboard.amenities.index')->with('status', __('aqdar.catalog.deactivated'));
    }

    public function tags(): View
    {
        return view('dashboard::reference.tags', [
            'tags' => Tag::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function storeTag(SaveCatalogRecordRequest $request): RedirectResponse
    {
        $this->masterData->createTag($request->validated());

        return redirect()->route('dashboard.tags.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function updateTag(SaveCatalogRecordRequest $request, Tag $tag): RedirectResponse
    {
        $this->masterData->updateTag($tag, $request->safe()->except(['image']));

        return redirect()->route('dashboard.tags.index')->with('status', __('aqdar.catalog.saved'));
    }

    public function destroyTag(Tag $tag): RedirectResponse
    {
        $this->masterData->deleteTag($tag);

        return redirect()->route('dashboard.tags.index')->with('status', __('aqdar.catalog.deactivated'));
    }
}
