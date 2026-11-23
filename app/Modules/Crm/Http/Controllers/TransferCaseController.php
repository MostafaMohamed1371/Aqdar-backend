<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Core\Enums\CaseDocumentKind;
use App\Core\Http\Controllers\BaseController;
use App\Modules\Crm\Http\Requests\UploadCaseDocumentRequest;
use App\Modules\Crm\Http\Resources\TransferCaseResource;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Crm\Services\CaseService;
use App\Modules\Crm\Services\MaskedCallService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransferCaseController extends BaseController
{
    public function __construct(private CaseService $cases) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return TransferCaseResource::collection($this->cases->forParty($user));
    }

    public function show(Request $request, TransferCase $transferCase): TransferCaseResource
    {
        /** @var User $user */
        $user = $request->user();

        return new TransferCaseResource($this->cases->showForParty($user, $transferCase));
    }

    public function storeDocument(UploadCaseDocumentRequest $request, TransferCase $transferCase): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $kind = CaseDocumentKind::tryFrom((string) $request->validated('kind', ''))
            ?? CaseDocumentKind::BuyerRequested;
        $document = $this->cases->storeDocument(
            $this->cases->showForParty($user, $transferCase),
            $request->file('file'),
            $kind,
            $user,
            null,
        );

        return response()->json([
            'data' => [
                'id' => $document->id,
                'kind' => $document->kind?->value,
                'original_name' => $document->original_name,
            ],
        ], 201);
    }

    public function startCall(Request $request, TransferCase $transferCase, MaskedCallService $calls): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $call = $calls->start($transferCase, $user);

        return response()->json([
            'data' => [
                'id' => $call->id,
                'status' => $call->status?->value,
                'proxy_number' => $call->proxy_number,
            ],
        ], 201);
    }
}
