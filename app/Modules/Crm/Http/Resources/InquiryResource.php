<?php

namespace App\Modules\Crm\Http\Resources;

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
            'sales_contact' => app(SettingService::class)->publicConfig()['sales_contact'],
            'next_steps' => [
                __('aqdar.crm.next_steps.assign'),
                __('aqdar.crm.next_steps.call'),
                __('aqdar.crm.next_steps.track'),
            ],
            'created_at' => $this->created_at,
        ];
    }
}
