<?php

namespace App\Modules\Crm\Services;

use App\Core\Enums\CaseStatus;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\Inquiry;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\User\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ActivityFeedService
{
    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function forBuyer(User $buyer, array $filters = []): LengthAwarePaginator
    {
        $status = (string) ($filters['status'] ?? '');
        $rows = collect();

        Inquiry::query()
            ->where('user_id', $buyer->id)
            ->with(['listing.media'])
            ->get()
            ->each(function (Inquiry $inquiry) use ($rows): void {
                $status = $inquiry->status?->value;
                $rows->push([
                    'id' => $inquiry->id,
                    'public_code' => $inquiry->public_code,
                    'kind' => 'listing_inquiry',
                    'status' => $status,
                    'label' => $status,
                    'listing' => $inquiry->listing?->publicCard(),
                    'created_at' => $inquiry->created_at,
                ]);
            });

        BuyerRequest::query()
            ->where('user_id', $buyer->id)
            ->with(['matches' => fn ($query) => $query->whereNotNull('sent_at')->with('listing.media')])
            ->get()
            ->each(function (BuyerRequest $request) use ($rows): void {
                $cover = $request->matches->first()?->listing;
                $status = $request->status?->clientStatus();
                $rows->push([
                    'id' => $request->id,
                    'public_code' => $request->public_code,
                    'kind' => 'buyer_request',
                    'status' => $status,
                    'label' => $status,
                    'listing' => $cover?->publicCard(),
                    'created_at' => $request->created_at,
                ]);
            });

        TransferCase::query()
            ->where(function ($query) use ($buyer): void {
                $query->where('buyer_id', $buyer->id)
                    ->orWhere('seller_id', $buyer->id);
            })
            ->with(['listing.media'])
            ->get()
            ->each(function (TransferCase $case) use ($rows): void {
                $chip = $case->status?->isClosed() || $case->status === CaseStatus::Completed ? 'closed' : 'in_review';
                $rows->push([
                    'id' => $case->id,
                    'public_code' => $case->public_code,
                    'kind' => 'case',
                    'status' => $chip,
                    'label' => $case->client_label,
                    'listing' => $case->listing?->publicCard(),
                    'created_at' => $case->created_at,
                ]);
            });

        if ($status !== '' && $status !== 'all') {
            $rows = $rows->filter(function (array $row) use ($status): bool {
                return (string) $row['status'] === $status
                    || ($status === 'in_review' && in_array($row['status'], ['new', 'in_review'], true))
                    || ($status === 'replied' && $row['status'] === 'replied')
                    || ($status === 'closed' && in_array($row['status'], ['closed', 'completed'], true));
            })->values();
        }

        $sorted = $rows->sortByDesc(fn (array $row) => $row['created_at']?->timestamp ?? 0)->values();
        $page = max(1, (int) request()->query('page', 1));
        $perPage = 20;
        $slice = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($slice, $sorted->count(), $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
