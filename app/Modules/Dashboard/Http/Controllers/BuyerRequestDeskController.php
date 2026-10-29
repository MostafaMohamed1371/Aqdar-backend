<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Enums\BuyerRequestStatus;
use App\Core\Enums\MatchBuyerResponse;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\RequestMatch;
use App\Modules\Crm\Services\BuyerRequestService;
use App\Modules\Dashboard\Http\Requests\AssignLeadRequest;
use App\Modules\Dashboard\Http\Requests\StoreDeskBuyerRequestRequest;
use App\Modules\Dashboard\Http\Requests\UpdateBuyerRequestStatusRequest;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuyerRequestDeskController extends BaseController
{
    public function __construct(private BuyerRequestService $requests) {}

    public function index(Request $request): View
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $filters = [
            'status' => (string) $request->query('status', 'all'),
            'type' => (string) $request->query('type', ''),
            'assigned_to' => (string) $request->query('assigned_to', ''),
            'q' => trim((string) $request->query('q', '')),
        ];

        return view('dashboard::requests.index', [
            'requests' => $this->requests->paginateFor($staff, $filters),
            'counts' => $this->requests->countsFor($staff),
            'tab' => $filters['status'] === '' ? 'all' : $filters['status'],
            'type' => $filters['type'],
            'assignedTo' => $filters['assigned_to'],
            'search' => $filters['q'],
            'assignable' => $this->requests->assignableStaff(),
            'canAssign' => $this->requests->canManageAll($staff),
        ]);
    }

    public function show(Request $request, BuyerRequest $buyerRequest): View
    {
        /** @var StaffUser $staff */
        $staff = $request->user();

        return view('dashboard::requests.show', [
            'requestModel' => $this->requests->showForStaff($staff, $buyerRequest),
            'assignable' => $this->requests->assignableStaff(),
            'canAssign' => $this->requests->canManageAll($staff),
        ]);
    }

    public function store(StoreDeskBuyerRequestRequest $request): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $created = $this->requests->createManual($staff, $request->validated());

        return redirect()
            ->route('dashboard.requests.show', $created)
            ->with('status', __('aqdar.requests.created'));
    }

    public function assign(AssignLeadRequest $request, BuyerRequest $buyerRequest): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->requests->assign($buyerRequest, $staff, (int) $request->validated('assigned_to'));

        return back()->with('status', __('aqdar.requests.assigned'));
    }

    public function status(UpdateBuyerRequestStatusRequest $request, BuyerRequest $buyerRequest): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->requests->setStatus(
            $buyerRequest,
            $staff,
            BuyerRequestStatus::from($request->validated('status')),
            $request->validated('reason'),
        );

        return back()->with('status', __('aqdar.requests.status_updated'));
    }

    public function refresh(Request $request, BuyerRequest $buyerRequest): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->requests->refreshMatches($buyerRequest, $staff);

        return back()->with('status', __('aqdar.requests.rematched'));
    }

    public function sendMatch(Request $request, BuyerRequest $buyerRequest, RequestMatch $match): RedirectResponse
    {
        abort_unless((int) $match->buyer_request_id === (int) $buyerRequest->id, 404);
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->requests->sendMatch($match, $staff);

        return back()->with('status', __('aqdar.requests.sent'));
    }

    public function matchResponse(Request $request, BuyerRequest $buyerRequest, RequestMatch $match): RedirectResponse
    {
        abort_unless((int) $match->buyer_request_id === (int) $buyerRequest->id, 404);
        $response = MatchBuyerResponse::from((string) $request->validate([
            'buyer_response' => ['required', 'in:interested,waiting,declined'],
        ])['buyer_response']);
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->requests->recordMatchResponse($match, $staff, $response);

        return back()->with('status', __('aqdar.requests.response_saved'));
    }

    public function openLead(Request $request, BuyerRequest $buyerRequest, RequestMatch $match): RedirectResponse
    {
        abort_unless((int) $match->buyer_request_id === (int) $buyerRequest->id, 404);
        /** @var StaffUser $staff */
        $staff = $request->user();
        $lead = $this->requests->openLeadFromMatch($match, $staff);

        return redirect()
            ->route('dashboard.leads.show', $lead)
            ->with('status', __('aqdar.crm.created'));
    }

    public function convert(Request $request, BuyerRequest $buyerRequest): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']])['reason'];
        $case = $this->requests->convertToCase($buyerRequest, $staff, $reason);

        return redirect()
            ->route('dashboard.cases.show', $case)
            ->with('status', __('aqdar.cases.opened'));
    }
}
