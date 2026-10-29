<?php

namespace App\Models;

use App\Modules\User\Models\User as ModuleUser;

/**
 * Backward-compatible alias for Laravel auth defaults.
 *
 * @deprecated Use App\Modules\User\Models\User directly in new code.
 */
class User extends ModuleUser
{
    //
}
