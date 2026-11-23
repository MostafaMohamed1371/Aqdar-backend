<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\CaseDocumentKind;
use App\Core\Enums\CaseStage;
use App\Core\Enums\CaseStatus;
use App\Core\Enums\ExtractionStatus;
use App\Core\Enums\Permission;
use App\Core\Http\Controllers\BaseController;
use App\Core\Services\QueueQuery;
use App\Modules\Crm\Http\Requests\UploadCaseDocumentRequest;
use App\Modules\Crm\Models\CaseDocument;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Crm\Services\CaseChecklistService;
use App\Modules\Crm\Services\CaseService;
use App\Modules\Crm\Services\Ocr\DocumentExtractionService;
use App\Modules\Dashboard\Http\Requests\AssignLeadRequest;
use App\Modules\Dashboard\Http\Requests\ReviewDocumentExtractionRequest;
use App\Modules\Dashboard\Http\Requests\StoreDeskCaseRequest;
use App\Modules\Dashboard\Http\Requests\UpdateCaseStageRequest;
use App\Modules\Listing\Services\DocumentWatermarker;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransferCaseDeskController extends BaseController
{
    public function __construct(
        private CaseService $cases,
        private DocumentWatermarker $watermarker,
        private AuditLogger $audit,
        private CaseChecklistService $checklist,
        private DocumentExtractionService $extractions,
    ) {}

    public function index(Request $request): View
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $filters = [
            'stage' => (string) $request->query('stage', 'all'),
            'status' => (string) $request->query('status', ''),
            'assigned_to' => (string) $request->query('assigned_to', ''),
            'q' => trim((string) $request->query('q', '')),
            ...QueueQuery::filtersFrom($request),
        ];

        return view('dashboard::cases.index', [
            'cases' => $this->cases->paginateFor($staff, $filters),
            'counts' => $this->cases->countsFor($staff),
            'tab' => $filters['stage'] === '' ? 'all' : $filters['stage'],
            'status' => $filters['status'],
            'assignedTo' => $filters['assigned_to'],
            'search' => $filters['q'],
            'assignable' => $this->cases->assignableStaff(),
            'canAssign' => $this->cases->canManageAll($staff),
            'canCreate' => $staff->can('manage_crm'),
            'financeOnly' => $this->cases->isFinanceOnly($staff),
        ]);
    }

    public function show(Request $request, TransferCase $transferCase): View
    {
        /** @var StaffUser $staff */
        $staff = $request->user();

        $case = $this->cases->showForStaff($staff, $transferCase);
        $financeOnly = $this->cases->isFinanceOnly($staff);

        $case->load(['documents.extraction', 'escrowPayments.recordedBy', 'escrowPayments.settledBy']);

        return view('dashboard::cases.show', [
            'caseModel' => $case,
            'assignable' => $this->cases->assignableStaff(),
            'canAssign' => $this->cases->canManageAll($staff),
            'financeOnly' => $financeOnly,
            'allowedStages' => $this->cases->allowedStagesFor($staff, $case),
            'canAdvanceFinance' => ! $financeOnly || $this->cases->isReadyForFinance($case),
            'checklist' => $financeOnly ? collect() : $this->checklist->itemsFor($case),
            'canManageEscrow' => $staff->canAny([
                Permission::ViewCommissions->value,
                Permission::EditCommissions->value,
                Permission::ApproveCommissions->value,
            ]),
            'canRecordEscrow' => $staff->hasPermission(Permission::EditCommissions),
            'canApproveEscrow' => $staff->hasPermission(Permission::ApproveCommissions),
            'canReviewDocuments' => ! $financeOnly,
        ]);
    }

    public function store(StoreDeskCaseRequest $request): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $case = $this->cases->createManual($staff, $request->validated());

        return redirect()
            ->route('dashboard.cases.show', $case)
            ->with('status', __('aqdar.cases.opened'));
    }

    public function assign(AssignLeadRequest $request, TransferCase $transferCase): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->cases->assign($transferCase, $staff, (int) $request->validated('assigned_to'), $request->validated('reason'));

        return back()->with('status', __('aqdar.cases.assigned'));
    }

    public function stage(UpdateCaseStageRequest $request, TransferCase $transferCase): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $data = $request->validated();
        $this->cases->setStage(
            $transferCase,
            $staff,
            CaseStage::from($data['stage']),
            $data['reason'],
            $data['internal_note'] ?? null,
        );
        if (! empty($data['requested_document_note']) && ! $this->cases->isFinanceOnly($staff)) {
            $this->cases->requestBuyerDocument($transferCase->fresh() ?? $transferCase, $staff, $data['requested_document_note']);
        }

        return back()->with('status', __('aqdar.cases.stage_updated'));
    }

    public function lifecycle(Request $request, TransferCase $transferCase): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $data = $request->validate([
            'status' => ['required', 'in:open,waiting_for_information,on_hold,cancelled,lost,completed'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $this->cases->setLifecycle($transferCase, $staff, CaseStatus::from($data['status']), $data['reason']);

        return back()->with('status', __('aqdar.cases.status_updated'));
    }

    public function storeDocument(UploadCaseDocumentRequest $request, TransferCase $transferCase): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $kind = CaseDocumentKind::tryFrom((string) ($request->validated()['kind'] ?? ''))
            ?? CaseDocumentKind::DeveloperApproval;
        $this->cases->storeDocument($transferCase, $request->file('file'), $kind, null, $staff);

        return back()->with('status', __('aqdar.cases.document_uploaded'));
    }

    public function checklist(Request $request, TransferCase $transferCase, string $item): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $data = $request->validate(['done' => ['required', 'boolean']]);
        $this->checklist->setDone($transferCase, $staff, $item, (bool) $data['done']);

        return back()->with('status', __('aqdar.checklist.updated'));
    }

    public function reviewExtraction(ReviewDocumentExtractionRequest $request, CaseDocument $caseDocument): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $case = $caseDocument->transferCase;
        abort_if($case === null, 404);
        $this->cases->assertCanAct($staff, $case);

        $data = $request->validated();
        $extraction = $caseDocument->extraction ?? $this->extractions->queue($caseDocument);
        $this->extractions->review(
            $extraction,
            $staff,
            ExtractionStatus::from($data['status']),
            (array) ($data['fields'] ?? []),
        );

        return back()->with('status', __('aqdar.extraction.reviewed'));
    }

    public function download(Request $request, CaseDocument $caseDocument): StreamedResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $case = $caseDocument->transferCase;
        abort_if($case === null, 404);
        $this->cases->assertCanAct($staff, $case);

        $stamp = 'AQDAR staff-'.$staff->id.' '.now()->format('Y-m-d H:i');
        $bytes = $this->watermarker->stamp($caseDocument->contents(), (string) $caseDocument->mime, $stamp);
        $this->audit->access('document.opened', $staff, $caseDocument, [
            'case_id' => $case->id,
            'watermark' => $stamp.' '.$staff->name,
        ]);
        $filename = ($case->public_code ?? 'case').'_'.($caseDocument->kind?->value ?? 'doc').'.'.pathinfo($caseDocument->original_name, PATHINFO_EXTENSION);

        return response()->streamDownload(function () use ($bytes): void {
            echo $bytes;
        }, $filename, [
            'Content-Type' => $caseDocument->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }
}
