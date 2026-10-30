<?php

namespace App\Modules\Listing\Models;

use App\Modules\Listing\Database\Factories\SavedSearchFactory;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'filters', 'notify', 'last_matched_at'])]
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    protected static function newFactory(): SavedSearchFactory
    {
        return SavedSearchFactory::new();
    }

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'notify' => 'boolean',
            'last_matched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
