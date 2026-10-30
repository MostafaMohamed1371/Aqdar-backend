<?php

namespace App\Modules\Crm\Http\Resources;

use App\Core\Enums\LeadStatus;
use App\Modules\Crm\Models\Inquiry;
use App\Modules\Setting\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Inquiry */
class InquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_code' => $this->public_code,
            'kind' => 'listing_inquiry',
            'inquiry_type' => $this->inquiry_type ?: 'interested',
            'status' => $this->status?->value,
            'contact_method' => $this->contact_method?->value,
            'preferred_time' => $this->preferred_time?->value,
            'message' => $this->message,
            'listing' => $this->listing?->publicCard(),
            'lead_code' => $this->whenLoaded('lead', fn () => $this->lead?->public_code),
            'lead_status' => $this->whenLoaded('lead', fn () => $this->lead?->status?->value),
            'timeline' => $this->whenLoaded('lead', fn () => $this->clientTimeline()),
            'consultant' => $this->whenLoaded('lead', fn () => $this->lead?->assignee ? [
                'name' => $this->lead->assignee->name,
                'role' => __('aqdar.requests.consultant_role'),
            ] : null),
            'sales_contact' => app(SettingService::class)->publicConfig()['sales_contact'],
            'next_steps' => [
                __('aqdar.crm.next_steps.assign'),
                __('aqdar.crm.next_steps.call'),
                __('aqdar.crm.next_steps.track'),
            ],
            'created_at' => $this->created_at,
        ];
    }

    /** @return list<array{key: string, state: string, at: mixed}> */
    private function clientTimeline(): array
    {
        $lead = $this->lead;
        $status = $lead?->status ?? LeadStatus::New;
        $steps = ['received', 'assigned', 'contacted', 'viewing', 'closed'];

        $current = match (true) {
            ! $status->isOpen() => 'closed',
            $status === LeadStatus::VisitScheduled, $status === LeadStatus::OfferSent => 'viewing',
            $status === LeadStatus::Contacted, $status === LeadStatus::Qualified => 'contacted',
            $lead?->assigned_to !== null => 'assigned',
            default => 'received',
        };
        $currentIndex = array_search($current, $steps, true);

        return collect($steps)->map(fn (string $key, int $index) => [
            'key' => $key,
            'state' => $index < $currentIndex || ($key === 'closed' && $current === 'closed')
                ? 'done'
                : ($index === $currentIndex ? 'current' : 'future'),
            'at' => match ($key) {
                'received' => $this->created_at,
                'contacted' => $index <= $currentIndex ? $lead?->last_contacted_at : null,
                default => null,
            },
        ])->all();
    }
}
