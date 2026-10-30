<?php

namespace App\Modules\Listing\Models;

use App\Core\Enums\ListingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['listing_id', 'from_status', 'to_status', 'actor_type', 'actor_id', 'reason', 'field_notes'])]
class ListingStatusHistory extends Model
{
    /** Seller wizard sections a reviewer can attach a note to. */
    public const NOTE_SECTIONS = ['category', 'specs', 'financials', 'media', 'documents'];

    public $timestamps = false;

    protected $table = 'listing_status_history';

    protected function casts(): array
    {
        return [
            'from_status' => ListingStatus::class,
            'to_status' => ListingStatus::class,
            'field_notes' => 'array',
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
