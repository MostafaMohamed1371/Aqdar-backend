<?php

namespace App\Modules\Listing\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Listing\Http\Requests\StoreSavedSearchRequest;
use App\Modules\Listing\Http\Requests\UpdateSavedSearchRequest;
use App\Modules\Listing\Http\Resources\SavedSearchResource;
use App\Modules\Listing\Models\SavedSearch;
use App\Modules\Listing\Services\SavedSearchService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SavedSearchController extends BaseController
{
    public function __construct(private SavedSearchService $savedSearches) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return SavedSearchResource::collection($this->savedSearches->forUser($user));
    }

    public function store(StoreSavedSearchRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $row = $this->savedSearches->create($user, $request->validated());

        return (new SavedSearchResource($row))->response()->setStatusCode(201);
    }

    public function update(UpdateSavedSearchRequest $request, SavedSearch $savedSearch): SavedSearchResource
    {
        /** @var User $user */
        $user = $request->user();

        return new SavedSearchResource($this->savedSearches->update($user, $savedSearch, $request->validated()));
    }

    public function destroy(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->savedSearches->delete($user, $savedSearch);

        return response()->json(['message' => __('aqdar.saved_searches.deleted')]);
    }
}
