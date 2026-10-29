<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Enums\Permission as PermissionEnum;
use App\Core\Enums\StaffRole;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Dashboard\Http\Requests\StoreStaffRequest;
use App\Modules\Dashboard\Http\Requests\UpdateStaffRequest;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\Staff\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends BaseController
{
    public function __construct(private StaffService $staffService) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('dashboard::staff.index', [
            'staffUsers' => $this->staffService->paginate($search),
            'roles' => $this->staffService->roles(),
            'stats' => $this->staffService->stats(),
            'matrix' => $this->staffService->permissionMatrix(),
            'permissions' => PermissionEnum::cases(),
            'staffRoles' => StaffRole::cases(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('dashboard::staff.create', [
            'roles' => $this->staffService->roles(),
        ]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        /** @var StaffUser $actor */
        $actor = $request->user();
        $this->staffService->create($request->validated(), $actor);

        return redirect()
            ->route('dashboard.staff.index')
            ->with('status', __('aqdar.staff.created'));
    }

    public function edit(StaffUser $staffUser): View
    {
        return view('dashboard::staff.edit', [
            'staffUser' => $staffUser,
            'roles' => $this->staffService->roles(),
        ]);
    }

    public function update(UpdateStaffRequest $request, StaffUser $staffUser): RedirectResponse
    {
        /** @var StaffUser $actor */
        $actor = $request->user();
        $this->staffService->update($staffUser, $request->validated(), $actor);

        return redirect()
            ->route('dashboard.staff.index')
            ->with('status', __('aqdar.staff.updated'));
    }

    public function destroy(Request $request, StaffUser $staffUser): RedirectResponse
    {
        /** @var StaffUser $actor */
        $actor = $request->user();
        $this->staffService->delete($staffUser, $actor);

        return redirect()
            ->route('dashboard.staff.index')
            ->with('status', __('aqdar.staff.deleted'));
    }

    public function deactivate(Request $request, StaffUser $staffUser): RedirectResponse
    {
        /** @var StaffUser $actor */
        $actor = $request->user();
        $this->staffService->deactivate($staffUser, $actor);

        return back()->with('status', __('aqdar.staff.deactivated'));
    }

    public function activate(Request $request, StaffUser $staffUser): RedirectResponse
    {
        /** @var StaffUser $actor */
        $actor = $request->user();
        $this->staffService->activate($staffUser, $actor);

        return back()->with('status', __('aqdar.staff.activated'));
    }
}
