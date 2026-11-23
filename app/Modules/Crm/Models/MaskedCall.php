<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\MaskedCallStatus;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'case_id',
    'caller_user_id',
    'provider',
    'status',
    'provider_session_id',
    'proxy_number',
])]
class MaskedCall extends Model
{
    protected function casts(): array
    {
        return [
            'status' => MaskedCallStatus::class,
        ];
    }

    public function transferCase(): BelongsTo
    {
        return $this->belongsTo(TransferCase::class, 'case_id');
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_user_id');
    }
}
