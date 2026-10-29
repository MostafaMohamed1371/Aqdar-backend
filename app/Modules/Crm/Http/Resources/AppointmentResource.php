<?php

namespace App\Modules\Crm\Http\Resources;

use App\Modules\Crm\Models\Appointment;
use App\Modules\Setting\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Appointment */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_code' => $this->public_code,
            'kind' => 'viewing',
            'status' => $this->status?->value,
            'requested_date' => $this->requested_date?->toDateString(),
            'requested_slot' => $this->requested_slot,
            'listing' => $this->listing?->publicCard(),
            'lead_code' => $this->whenLoaded('lead', fn () => $this->lead?->public_code),
            'sales_contact' => app(SettingService::class)->publicConfig()['sales_contact'],
            'created_at' => $this->created_at,
        ];
    }
}
