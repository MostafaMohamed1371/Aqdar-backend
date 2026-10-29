<?php

namespace App\Modules\Listing\Models;

use App\Core\Enums\DocumentReviewStatus;
use App\Core\Enums\DocumentType;
use App\Modules\Listing\Database\Factories\ListingDocumentFactory;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'listing_id',
    'type',
    'version',
    'disk',
    'path',
    'original_name',
    'mime',
    'size_bytes',
    'review_status',
    'review_note',
    'reviewed_by',
    'reviewed_at',
])]
class ListingDocument extends Model
{
    /** @use HasFactory<ListingDocumentFactory> */
    use HasFactory;

    protected static function newFactory(): ListingDocumentFactory
    {
        return ListingDocumentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'version' => 'integer',
            'size_bytes' => 'integer',
            'review_status' => DocumentReviewStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'reviewed_by');
    }

    public function contents(): string
    {
        return Storage::disk($this->disk)->get($this->path);
    }
}
