<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Crm\Services\LeadService;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Dashboard\Services\ReportService;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportDeskController extends BaseController
{
    public function __construct(
        private ReportService $reports,
        private LeadService $leads,
        private DashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $kpis = $this->reports->kpis($filters);

        return view('dashboard::reports.index', [
            'filters' => $filters,
            'kpis' => $kpis,
            'deltas' => $this->reports->deltas($filters),
            'rates' => $this->reports->rates($filters),
            'types' => ReportService::TYPES,
            'charts' => $this->reports->overviewSeries($filters),
            'owners' => $this->leads->assignableStaff(),
            'canReveal' => $request->user()?->can('reveal_phone') ?? false,
        ]);
    }

    public function export(Request $request): StreamedResponse|Response
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $filters = $this->filters($request);
        $type = (string) $request->query('type', 'listings');
        $includePhones = $request->boolean('include_phones');

        if ($request->query('format') === 'pdf') {
            return $this->reports->exportPdf($staff, $type, $filters, $includePhones);
        }

        return $this->reports->exportCsv($staff, $type, $filters, $includePhones);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        [$start, $end, $preset] = $this->dashboard->resolveWindow(
            $request->query('from'),
            $request->query('to'),
            $request->query('preset'),
            'month',
        );

        return [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'preset' => $preset,
            'status' => (string) $request->query('status', 'all'),
            'source' => (string) $request->query('source', 'all'),
            'owner' => (string) $request->query('owner', ''),
            'segment' => (string) $request->query('segment', 'all'),
        ];
    }
}
