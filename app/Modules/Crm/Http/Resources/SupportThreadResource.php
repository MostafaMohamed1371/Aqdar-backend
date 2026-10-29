<?php

namespace App\Modules\Crm\Http\Resources;

use App\Core\Enums\SupportAuthorType;
use App\Modules\Crm\Models\SupportThread;
use App\Modules\Setting\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupportThread */
class SupportThreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_code' => $this->public_code,
            'status' => $this->status?->value,
            'sales_contact' => app(SettingService::class)->publicConfig()['sales_contact'],
            'messages' => $this->whenLoaded('messages', function () {
                return $this->messages->map(fn ($message) => [
                    'id' => $message->id,
                    'author' => $message->author_type === SupportAuthorType::Staff ? 'sales' : 'user',
                    'body' => $message->body,
                    'created_at' => $message->created_at,
                ])->values();
            }),
            'created_at' => $this->created_at,
        ];
    }
}
