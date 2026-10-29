<?php

namespace App\Modules\Auth\Http\Resources;

use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin array{user: User, token: string} */
class AuthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->resource['user']),
            'token' => $this->resource['token'],
            'token_type' => 'Bearer',
        ];
    }
}
