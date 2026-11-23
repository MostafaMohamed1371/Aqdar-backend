<?php

namespace App\Modules\Crm\Http\Resources;

use App\Core\Enums\BuyerRequestStatus;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Setting\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BuyerRequest */
class BuyerRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sent = $this->relationLoaded('matches')
            ? $this->matches->filter(fn ($match) => $match->sent_at !== null)->values()
            : collect();

        return [
            'id' => $this->id,
            'public_code' => $this->public_code,
            'kind' => 'buyer_request',
            'status' => $this->status?->clientStatus(),
            'intent' => $this->intent?->value,
            'segment_preference' => $this->segment_preference?->value,
            'unit_type' => $this->unitType?->localizedName(),
            'governorate' => $this->governorate?->localizedName(),
            'area' => $this->area?->localizedName(),
            'developer' => $this->developer?->localizedName(),
            'project' => $this->project?->localizedName(),
            'budget_min' => $this->budget_min !== null ? (float) $this->budget_min : null,
            'budget_max' => $this->budget_max !== null ? (float) $this->budget_max : null,
            'area_sqm_min' => $this->area_sqm_min !== null ? (float) $this->area_sqm_min : null,
            'area_sqm_max' => $this->area_sqm_max !== null ? (float) $this->area_sqm_max : null,
            'bedrooms' => $this->bedrooms,
            'finishing' => $this->finishing,
            'down_payment' => $this->down_payment !== null ? (float) $this->down_payment : null,
            'monthly_installment' => $this->monthly_installment !== null ? (float) $this->monthly_installment : null,
            'duration_years' => $this->duration_years,
            'purchase_timeline' => $this->timeline,
            'priorities' => $this->priorities,
            'contact_method' => $this->contact_method?->value,
            'options' => $sent->map(function ($match) {
                $listing = $match->listing;

                return [
                    'listing' => $listing?->publicCard(),
                    'asking_price' => $listing?->publicAskingPrice() !== null ? (float) $listing->publicAskingPrice() : null,
                    'score' => $match->score,
                    'sent_at' => $match->sent_at,
                ];
            })->values(),
            'timeline' => $this->clientTimeline(),
            'consultant' => $this->assignee ? [
                'name' => $this->assignee->name,
                'role' => __('aqdar.requests.consultant_role'),
            ] : null,
            'sales_contact' => app(SettingService::class)->salesContactFor($this->assignee),
            'created_at' => $this->created_at,
        ];
    }

    /** @return list<array{key: string, state: string}> */
    private function clientTimeline(): array
    {
        $status = $this->status ?? BuyerRequestStatus::New;
        $order = [
            'received' => true,
            'in_review' => in_array($status, [
                BuyerRequestStatus::New, BuyerRequestStatus::Assigned, BuyerRequestStatus::ContactingBuyer,
                BuyerRequestStatus::Matching, BuyerRequestStatus::OptionsShared, BuyerRequestStatus::NegotiationVisit,
                BuyerRequestStatus::Converted, BuyerRequestStatus::ClosedNoMatch,
            ], true),
            'matching' => in_array($status, [
                BuyerRequestStatus::Matching, BuyerRequestStatus::OptionsShared, BuyerRequestStatus::NegotiationVisit,
                BuyerRequestStatus::Converted, BuyerRequestStatus::ClosedNoMatch,
            ], true),
            'contact' => in_array($status, [
                BuyerRequestStatus::ContactingBuyer, BuyerRequestStatus::OptionsShared, BuyerRequestStatus::NegotiationVisit,
                BuyerRequestStatus::Converted, BuyerRequestStatus::ClosedNoMatch,
            ], true),
            'closed' => in_array($status, [BuyerRequestStatus::Converted, BuyerRequestStatus::ClosedNoMatch], true),
        ];
        $current = match ($status) {
            BuyerRequestStatus::New, BuyerRequestStatus::Assigned => 'in_review',
            BuyerRequestStatus::Matching => 'matching',
            BuyerRequestStatus::ContactingBuyer, BuyerRequestStatus::OptionsShared, BuyerRequestStatus::NegotiationVisit => 'contact',
            BuyerRequestStatus::Converted, BuyerRequestStatus::ClosedNoMatch => 'closed',
        };

        return collect($order)->map(fn (bool $done, string $key) => [
            'key' => $key,
            'state' => $key === $current ? 'current' : ($done && $key !== $current ? 'done' : 'future'),
        ])->values()->all();
    }
}
