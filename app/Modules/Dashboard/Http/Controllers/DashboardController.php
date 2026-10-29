<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function __construct(private DashboardService $dashboardService) {}

    public function index(Request $request): View
    {
        if (! auth()->user() instanceof StaffUser) {
            return view('dashboard::login');
        }

        $chartMonths = (int) $request->query('chart_months', 6);
        $overview = $this->dashboardService->overview(
            $request->query('from'),
            $request->query('to'),
            $request->query('preset'),
            $chartMonths,
        );

        return view('dashboard::index', [
            'overview' => $overview,
            'stats' => $overview['kpis'],
            'deltas' => $overview['deltas'],
            'from' => $overview['from'],
            'to' => $overview['to'],
            'preset' => $overview['preset'],
            'chartMonths' => $overview['chart_months'],
            'needsAction' => $overview['needs_action'],
            'funnel' => $overview['funnel'],
            'charts' => $overview['charts'],
        ]);
    }
}
