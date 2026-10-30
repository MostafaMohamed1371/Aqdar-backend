<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Enums\Permission as PermissionEnum;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Dashboard\Http\Requests\UpdateProfileRequest;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\Staff\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class ProfileController extends BaseController
{
    public function __construct(private StaffService $staffService) {}

    public function show(Request $request): View
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $staff->loadMissing('role');

        return view('dashboard::profile.show', [
            'staffUser' => $staff,
            'roles' => $staff->hasPermission(PermissionEnum::ManageStaff)
                ? $this->staffService->roles()
                : collect(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $photo = $request->file('photo');

        $this->staffService->updateOwnProfile(
            $staff,
            $request->safe()->except(['photo', 'remove_photo']),
            $photo instanceof UploadedFile ? $photo : null,
            $request->boolean('remove_photo'),
        );
        $request->session()->put('staff_session_version', (int) $staff->fresh()->session_version);

        return redirect()
            ->route('dashboard.profile.show')
            ->with('status', __('aqdar.profile.updated'));
    }
}
