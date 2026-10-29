<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Dashboard\Services\AuditSearchService;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditDeskController extends BaseController
{
    public function __construct(private AuditSearchService $audit) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('dashboard::audit.index', [
            'filters' => $filters,
            'rows' => $this->audit->paginate($filters),
            'actors' => StaffUser::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->audit->exportCsv($this->filters($request));
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'kind' => (string) $request->query('kind', 'all'),
            'action' => (string) $request->query('action', 'all'),
            'actor_id' => (string) $request->query('actor_id', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];
    }
}
