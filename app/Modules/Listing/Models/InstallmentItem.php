<?php

namespace App\Modules\Listing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['listing_id', 'sequence', 'due_date', 'amount', 'note', 'paid', 'evidence_document_id'])]
class InstallmentItem extends Model
{
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid' => 'boolean',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(ListingDocument::class, 'evidence_document_id');
    }
}
