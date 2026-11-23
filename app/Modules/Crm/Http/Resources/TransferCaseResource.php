<?php

namespace App\Modules\Crm\Http\Resources;

use App\Core\Enums\CaseStage;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Setting\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TransferCase */
class TransferCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $current = $this->internal_stage ?? CaseStage::NewClient;
        $user = $request->user();
        $viewer = null;
        if ($user !== null && (int) $user->id === (int) $this->buyer_id) {
            $viewer = 'buyer';
        } elseif ($user !== null && $this->seller_id !== null && (int) $user->id === (int) $this->seller_id) {
            $viewer = 'seller';
        }

        return [
            'id' => $this->id,
            'public_code' => $this->public_code,
            'kind' => 'case',
            'status' => $this->client_label,
            'lifecycle' => $this->status?->value,
            'viewer' => $viewer,
            'listing' => $this->listing?->publicCard(),
            'requested_document_note' => $this->requested_document_note,
            'timeline' => collect(CaseStage::pipeline())->map(function (CaseStage $stage) use ($current) {
                $currentIndex = array_search($current, CaseStage::pipeline(), true);
                $index = array_search($stage, CaseStage::pipeline(), true);
                $state = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : 'future');

                return [
                    'key' => $stage->clientLabel(),
                    'state' => $state,
                    'at' => $this->reachedAt($stage),
                ];
            })->values(),
            'sales_contact' => app(SettingService::class)->salesContactFor($this->assignee),
            'created_at' => $this->created_at,
        ];
    }

    private function reachedAt(CaseStage $stage): ?string
    {
        if (! $this->relationLoaded('history')) {
            return null;
        }

        $row = $this->history->first(function ($item) use ($stage) {
            return $item->new_stage === $stage && $item->new_status === null;
        }) ?? $this->history->first(fn ($item) => $item->new_stage === $stage);

        return $row?->created_at?->toIso8601String();
    }
}
