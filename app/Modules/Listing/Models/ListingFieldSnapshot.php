<?php

namespace App\Modules\Listing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['listing_id', 'version', 'payload'])]
class ListingFieldSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'payload' => 'array',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
