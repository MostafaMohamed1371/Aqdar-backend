<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Audit\Models\AuditLog;
use App\Core\Http\Controllers\BaseController;
use App\Core\Identity\ContactMask;
use App\Modules\Dashboard\Http\Requests\StoreAdminUserRequest;
use App\Modules\Dashboard\Http\Requests\SuspendUserRequest;
use App\Modules\Dashboard\Http\Requests\UpdateAdminUserRequest;
use App\Modules\Staff\Models\StaffUser;
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

        $users = $this->userService->paginateForAdmin($search);

        return view('dashboard::users.index', [
            'users' => $users,
            'stats' => $this->userService->stats(),
            'search' => $search,
            'revealedPhones' => session('revealed_phones', []),
            'revealedEmails' => session('revealed_emails', []),
            'duplicates' => $this->userService->possibleDuplicates($users->items()),
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
            'user' => $user->load('notificationPreference'),
            'history' => AuditLog::query()
                ->where(function ($query) use ($user): void {
                    $query->where(fn ($subject) => $subject->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id))
                        ->orWhere(fn ($actor) => $actor->where('actor_type', $user->getMorphClass())->where('actor_id', $user->id));
                })
                ->latest('id')
                ->limit(25)
                ->get(),
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
        $email = $this->userService->revealEmail($user, $staff);

        return back()
            ->with('revealed_phones', [$user->id => $phone !== '' ? $phone : ContactMask::phone($user->phone)])
            ->with('revealed_emails', [$user->id => $email]);
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

    public function completeClose(Request $request, User $user): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->userService->completeClose($user, $staff);

        return back()->with('status', __('aqdar.users.close_completed'));
    }

    public function declineClose(Request $request, User $user): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->userService->declineClose($user, $staff);

        return back()->with('status', __('aqdar.users.close_declined'));
    }
}
