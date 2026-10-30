<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\ContactedParty;
use App\Core\Enums\LeadActivityType;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

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

    /**
     * Follow-ups whose due time has passed with no later activity on the same lead.
     */
    public function scopeOverdueFollowUp(Builder $query): Builder
    {
        return $query->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotExists(function (QueryBuilder $later): void {
                $later->from('lead_activities as later_activity')
                    ->whereColumn('later_activity.lead_id', 'lead_activities.lead_id')
                    ->whereColumn('later_activity.created_at', '>', 'lead_activities.due_at');
            });
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
