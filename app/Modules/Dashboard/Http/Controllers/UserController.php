<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Core\Identity\ContactMask;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\Dashboard\Http\Requests\StoreAdminUserRequest;
use App\Modules\Dashboard\Http\Requests\SuspendUserRequest;
use App\Modules\Dashboard\Http\Requests\UpdateAdminUserRequest;
use App\Modules\User\Models\User;
use App\Modules\User\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends BaseController
{
    public function __construct(private UserService $userService) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('dashboard::users.index', [
            'users' => $this->userService->paginateForAdmin($search),
            'stats' => $this->userService->stats(),
            'search' => $search,
            'revealedPhones' => session('revealed_phones', []),
        ]);
    }

    public function create(): View
    {
        return view('dashboard::users.create');
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->userService->createByAdmin($request->validated(), $staff);

        return redirect()
            ->route('dashboard.users.index')
            ->with('status', __('aqdar.users.created'));
    }

    public function edit(User $user): View
    {
        return view('dashboard::users.edit', [
            'user' => $user,
        ]);
    }

    public function update(UpdateAdminUserRequest $request, User $user): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->userService->updateByAdmin($user, $request->validated(), $staff);

        return redirect()
            ->route('dashboard.users.index')
            ->with('status', __('aqdar.users.updated'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->can('suspend_user'), 403);

        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->userService->deleteByAdmin($user, $staff);

        return redirect()
            ->route('dashboard.users.index')
            ->with('status', __('aqdar.users.deleted'));
    }

    public function reveal(Request $request, User $user): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $phone = $this->userService->revealPhone($user, $staff);

        return back()->with('revealed_phones', [
            $user->id => $phone !== '' ? $phone : ContactMask::phone($user->phone),
        ]);
    }

    public function suspend(SuspendUserRequest $request, User $user): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $reason = $request->validated('reason');

        $this->userService->suspend($user, $reason, $staff);

        return back()->with('status', __('aqdar.users.suspended'));
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->userService->reactivate($user, $staff);

        return back()->with('status', __('aqdar.users.reactivated'));
    }
}
