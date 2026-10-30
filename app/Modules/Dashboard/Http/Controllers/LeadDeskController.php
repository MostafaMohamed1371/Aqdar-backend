<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\LeadStatus;
use App\Core\Http\Controllers\BaseController;
use App\Core\Services\QueueQuery;
use App\Modules\Crm\Models\Appointment;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Services\AppointmentService;
use App\Modules\Crm\Services\LeadService;
use App\Modules\Dashboard\Http\Requests\AssignLeadRequest;
use App\Modules\Dashboard\Http\Requests\StoreDeskLeadRequest;
use App\Modules\Dashboard\Http\Requests\StoreLeadActivityRequest;
use App\Modules\Dashboard\Http\Requests\UpdateDeskAppointmentRequest;
use App\Modules\Dashboard\Http\Requests\UpdateLeadStatusRequest;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadDeskController extends BaseController
{
    public function __construct(
        private LeadService $leads,
        private AppointmentService $appointments,
    ) {}

    public function index(Request $request): View
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $filters = [
            'status' => (string) $request->query('status', 'all'),
            'source' => (string) $request->query('source', ''),
            'priority' => (string) $request->query('priority', ''),
            'assigned_to' => (string) $request->query('assigned_to', ''),
            'q' => trim((string) $request->query('q', '')),
            ...QueueQuery::filtersFrom($request),
        ];

        return view('dashboard::leads.index', [
            'leads' => $this->leads->paginateFor($staff, $filters),
            'counts' => $this->leads->countsFor($staff),
            'tab' => $filters['status'] === '' ? 'all' : $filters['status'],
            'source' => $filters['source'],
            'priority' => $filters['priority'],
            'assignedTo' => $filters['assigned_to'],
            'search' => $filters['q'],
            'assignable' => $this->leads->assignableStaff(),
            'canAssign' => $this->leads->canManageAll($staff),
            'revealed' => session('revealed_lead_phones', []),
        ]);
    }

    public function show(Request $request, Lead $lead): View
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->leads->assertCanAct($staff, $lead);

        $lead->load(['buyer', 'seller', 'listing', 'assignee', 'activities.actor', 'inquiries', 'appointments', 'transferCases']);

        return view('dashboard::leads.show', [
            'lead' => $lead,
            'assignable' => $this->leads->assignableStaff(),
            'canAssign' => $this->leads->canManageAll($staff),
            'revealed' => session('revealed_lead_phones', [])[$lead->id] ?? [],
        ]);
    }

    public function store(StoreDeskLeadRequest $request): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $data = $request->validated();

        if (! empty($data['assigned_to']) && (int) $data['assigned_to'] !== (int) $staff->id) {
            $this->leads->assertCanManageAll($staff);
        }

        $lead = $this->leads->createManual($staff, $data);

        return redirect()
            ->route('dashboard.leads.show', $lead)
            ->with('status', __('aqdar.crm.created'));
    }

    public function assign(AssignLeadRequest $request, Lead $lead): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->leads->assign($lead, $staff, (int) $request->validated('assigned_to'), $request->validated('reason'));

        return back()->with('status', __('aqdar.crm.assigned'));
    }

    public function activity(StoreLeadActivityRequest $request, Lead $lead): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->leads->addActivity($lead, $staff, $request->validated());

        return back()->with('status', __('aqdar.crm.activity_added'));
    }

    public function status(UpdateLeadStatusRequest $request, Lead $lead): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $status = LeadStatus::from($request->validated('status'));
        $reason = $request->validated('reason');

        if ($status === LeadStatus::Lost) {
            $this->leads->markLost($lead, $staff, $reason);
        } elseif ($status === LeadStatus::Converted) {
            $this->leads->convert($lead, $staff, $reason);
        } else {
            $this->leads->setStatus($lead, $staff, $status);
        }

        return back()->with('status', __('aqdar.crm.status_updated'));
    }

    public function appointment(UpdateDeskAppointmentRequest $request, Lead $lead, Appointment $appointment): RedirectResponse
    {
        abort_unless((int) $appointment->lead_id === (int) $lead->id, 404);

        /** @var StaffUser $staff */
        $staff = $request->user();
        $status = AppointmentStatus::from($request->validated('status'));
        $this->appointments->setStatus($staff, $appointment, $status);

        return back()->with('status', __('aqdar.crm.appointment_updated'));
    }

    public function reveal(Request $request, Lead $lead): RedirectResponse
    {
        $party = (string) $request->input('party', 'buyer');
        abort_unless(in_array($party, ['buyer', 'seller'], true), 422);

        /** @var StaffUser $staff */
        $staff = $request->user();
        $phone = $this->leads->revealParty($lead, $staff, $party);

        $revealed = session('revealed_lead_phones', []);
        $revealed[$lead->id][$party] = $phone;

        return back()->with('revealed_lead_phones', $revealed);
    }
}
