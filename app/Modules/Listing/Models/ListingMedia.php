<?php

namespace App\Modules\Listing\Models;

use App\Core\Enums\MediaModerationStatus;
use App\Modules\Listing\Database\Factories\ListingMediaFactory;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'listing_id',
    'sort_order',
    'original_disk',
    'original_path',
    'public_path',
    'mime',
    'size_bytes',
    'width',
    'height',
    'caption',
    'moderation_status',
    'hold_reason',
    'review_note',
    'reviewed_by',
    'reviewed_at',
])]
class ListingMedia extends Model
{
    /** @use HasFactory<ListingMediaFactory> */
    use HasFactory;

    protected $table = 'listing_media';

    protected static function newFactory(): ListingMediaFactory
    {
        return ListingMediaFactory::new();
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'moderation_status' => MediaModerationStatus::class,
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

    public function isApproved(): bool
    {
        return $this->moderation_status === MediaModerationStatus::Approved && filled($this->public_path);
    }

    public function publicUrl(): ?string
    {
        if (! $this->isApproved()) {
            return null;
        }

        return Storage::disk(config('aqdar.files.media.public_disk'))->url($this->public_path);
    }
}
