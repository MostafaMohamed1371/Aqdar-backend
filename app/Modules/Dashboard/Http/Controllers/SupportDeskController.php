<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Crm\Models\SupportThread;
use App\Modules\Crm\Services\SupportService;
use App\Modules\Dashboard\Http\Requests\StoreSupportReplyRequest;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportDeskController extends BaseController
{
    public function __construct(private SupportService $support) {}

    public function index(Request $request): View
    {
        /** @var StaffUser $staff */
        $staff = $request->user();

        return view('dashboard::support.index', [
            'threads' => $this->support->deskIndex($staff, [
                'status' => (string) $request->query('status', 'all'),
            ]),
            'status' => (string) $request->query('status', 'all'),
        ]);
    }

    public function show(Request $request, SupportThread $supportThread): View
    {
        /** @var StaffUser $staff */
        $staff = $request->user();

        return view('dashboard::support.show', [
            'thread' => $this->support->deskShow($staff, $supportThread),
        ]);
    }

    public function reply(StoreSupportReplyRequest $request, SupportThread $supportThread): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->support->reply($staff, $supportThread, (string) $request->validated('body'));

        return back()->with('status', __('aqdar.support.replied'));
    }

    public function close(Request $request, SupportThread $supportThread): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->support->close($staff, $supportThread);

        return redirect()->route('dashboard.support.index')->with('status', __('aqdar.support.closed_ok'));
    }
}
