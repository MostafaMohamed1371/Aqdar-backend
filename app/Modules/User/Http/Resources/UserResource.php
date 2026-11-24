<?php

namespace App\Modules\User\Http\Resources;

use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_verified_at' => $this->phone_verified_at,
            'account_type' => $this->account_type?->value ?? $this->account_type,
            'is_buyer' => (bool) $this->is_buyer,
            'is_seller' => (bool) $this->is_seller,
            'identity_status' => $this->identity_status?->value ?? $this->identity_status,
            'locale' => $this->locale,
            'region' => [
                'code' => config('aqdar.region.code'),
                'name' => __('aqdar.region.'.config('aqdar.region.code')),
            ],
            'profile_completed' => $this->isProfileComplete(),
            'two_factor_enabled' => (bool) $this->two_factor_enabled,
            'consent_privacy_at' => $this->consent_privacy_at,
            'consent_terms_at' => $this->consent_terms_at,
            'consent_version' => $this->consent_version,
            'is_suspended' => $this->isSuspended(),
            'is_close_pending' => $this->isClosePending(),
            'is_closed' => $this->isClosed(),
            'close_requested_at' => $this->close_requested_at,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
