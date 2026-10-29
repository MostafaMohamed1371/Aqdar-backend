<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\ContactedParty;
use App\Core\Enums\LeadActivityType;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lead_id',
    'actor_id',
    'type',
    'contacted_party',
    'body',
    'due_at',
])]
class LeadActivity extends Model
{
    protected function casts(): array
    {
        return [
            'type' => LeadActivityType::class,
            'contacted_party' => ContactedParty::class,
            'due_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'actor_id');
    }
}
