<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Modules\User\Models\User;

class UpdateAdminUserRequest extends StoreAdminUserRequest
{
    protected function ignoredUserId(): ?int
    {
        $user = $this->route('user');

        return $user instanceof User ? $user->id : null;
    }
}
