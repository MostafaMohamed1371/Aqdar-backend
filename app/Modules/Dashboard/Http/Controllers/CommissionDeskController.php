<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Dashboard\Http\Requests\StoreCommissionRequest;
use App\Modules\Dashboard\Http\Requests\UpdateCommissionRequest;
use App\Modules\Finance\Models\Commission;
use App\Modules\Finance\Services\CommissionService;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommissionDeskController extends BaseController
{
    public function __construct(private CommissionService $commissions) {}

    public function index(Request $request): View
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $filters = [
            'status' => (string) $request->query('status', 'all'),
            'q' => trim((string) $request->query('q', '')),
            'month' => (string) $request->query('month', ''),
        ];

        return view('dashboard::commissions.index', [
            'commissions' => $this->commissions->paginate($staff, $filters),
            'counts' => $this->commissions->counts($staff, ['month' => $filters['month']]),
            'charts' => $this->commissions->charts($staff, $filters),
            'monthOptions' => $this->commissions->monthOptions(),
            'status' => $filters['status'] === '' ? 'all' : $filters['status'],
            'search' => $filters['q'],
            'month' => $filters['month'],
            'defaultRate' => $this->commissions->defaultRate(),
            'latestChange' => $this->commissions->latestChange(),
            'closedCases' => $this->commissions->availableCases(),
        ]);
    }

    public function store(StoreCommissionRequest $request): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->commissions->create($staff, $request->validated());

        return redirect()
            ->route('dashboard.commissions.index')
            ->with('status', __('aqdar.commissions.created'));
    }

    public function update(UpdateCommissionRequest $request, Commission $commission): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->commissions->update($commission, $staff, $request->validated());

        return back()->with('status', __('aqdar.commissions.updated'));
    }

    public function export(Request $request): StreamedResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();

        return $this->commissions->exportCsv($staff, [
            'status' => (string) $request->query('status', 'all'),
            'q' => trim((string) $request->query('q', '')),
            'month' => (string) $request->query('month', ''),
        ]);
    }
}
