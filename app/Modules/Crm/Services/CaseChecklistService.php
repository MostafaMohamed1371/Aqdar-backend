<?php

namespace App\Modules\Crm\Services;

use App\Core\Audit\AuditLogger;
use App\Modules\Crm\Models\CaseChecklistItem;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Dashboard\Support\DeskRealtime;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Collection;

class CaseChecklistService
{
    public function __construct(
        private CaseService $cases,
        private AuditLogger $audit,
    ) {}

    /** @return Collection<int, CaseChecklistItem> */
    public function itemsFor(TransferCase $case): Collection
    {
        $keys = (array) config('aqdar.transfer_checklist', []);
        foreach ($keys as $key) {
            CaseChecklistItem::query()->firstOrCreate(['case_id' => $case->id, 'item_key' => $key]);
        }

        return $case->checklistItems()
            ->get()
            ->sortBy(fn (CaseChecklistItem $item): int => (int) array_search($item->item_key, $keys, true))
            ->values();
    }

    public function setDone(TransferCase $case, StaffUser $actor, string $key, bool $done): CaseChecklistItem
    {
        abort_unless(in_array($key, (array) config('aqdar.transfer_checklist', []), true), 404);
        abort_if($this->cases->isFinanceOnly($actor), 403, __('aqdar.http.forbidden'));
        $this->cases->assertCanAct($actor, $case);

        $item = $this->itemsFor($case)->firstWhere('item_key', $key);
        $item->forceFill([
            'done_at' => $done ? now() : null,
            'done_by_staff_id' => $done ? $actor->id : null,
        ])->save();

        $this->audit->write('case.checklist', $actor, $case, ['item' => $key, 'done' => $done]);
        DeskRealtime::push('cases', 'checklist', (int) $case->id, meta: ['item' => $key]);

        return $item;
    }
}
