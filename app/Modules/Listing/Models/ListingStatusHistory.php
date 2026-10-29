<?php

namespace App\Modules\Listing\Models;

use App\Core\Enums\ListingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['listing_id', 'from_status', 'to_status', 'actor_type', 'actor_id', 'reason'])]
class ListingStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'listing_status_history';

    protected function casts(): array
    {
        return [
            'from_status' => ListingStatus::class,
            'to_status' => ListingStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
