<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Dashboard\Http\Requests\SaveStaffTeamRequest;
use App\Modules\Staff\Models\StaffTeam;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\Staff\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffTeamController extends BaseController
{
    public function __construct(private StaffService $staffService) {}

    public function store(SaveStaffTeamRequest $request): RedirectResponse
    {
        /** @var StaffUser $actor */
        $actor = $request->user();
        $this->staffService->createTeam($request->validated(), $actor);

        return redirect()->route('dashboard.staff.index')->with('status', __('aqdar.staff.team_saved'));
    }

    public function update(SaveStaffTeamRequest $request, StaffTeam $staffTeam): RedirectResponse
    {
        /** @var StaffUser $actor */
        $actor = $request->user();
        $this->staffService->updateTeam($staffTeam, $request->validated(), $actor);

        return redirect()->route('dashboard.staff.index')->with('status', __('aqdar.staff.team_saved'));
    }

    public function destroy(Request $request, StaffTeam $staffTeam): RedirectResponse
    {
        /** @var StaffUser $actor */
        $actor = $request->user();
        $this->staffService->retireTeam($staffTeam, $actor);

        return redirect()->route('dashboard.staff.index')->with('status', __('aqdar.staff.team_retired'));
    }
}
