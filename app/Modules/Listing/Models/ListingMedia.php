<?php

namespace App\Modules\Listing\Models;

use App\Core\Enums\MediaModerationStatus;
use App\Core\Support\PublicMediaUrl;
use App\Modules\Listing\Database\Factories\ListingMediaFactory;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

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

    public function isVideo(): bool
    {
        $mime = strtolower((string) $this->mime);
        if (str_starts_with($mime, 'video/')) {
            return true;
        }

        $extension = strtolower(pathinfo((string) $this->original_path, PATHINFO_EXTENSION));

        return in_array($extension, ['mp4', 'webm'], true);
    }

    public function isApproved(): bool
    {
        return $this->moderation_status === MediaModerationStatus::Approved && filled($this->public_path);
    }

    /**
     * Marketplace / approved-only URL.
     */
    public function publicUrl(): ?string
    {
        if (! $this->isApproved()) {
            return null;
        }

        return PublicMediaUrl::for($this->public_path);
    }

    /**
     * File URL when the asset already lives on the public_media disk (set on upload).
     */
    public function storedPublicUrl(): ?string
    {
        if (! filled($this->public_path)) {
            return null;
        }

        return PublicMediaUrl::for($this->public_path);
    }

    /**
     * URL mobile can load in an image/video widget.
     * Sellers see public /storage/media URLs as soon as they upload; marketplace still uses isApproved().
     */
    public function displayUrl(?User $viewer = null): ?string
    {
        if ($this->isApproved()) {
            return $this->publicUrl();
        }

        $stored = $this->storedPublicUrl();
        if ($stored !== null) {
            if ($viewer === null) {
                return null;
            }
            $this->loadMissing('listing');
            if ((int) $this->listing?->user_id === (int) $viewer->id) {
                return $stored;
            }

            return null;
        }

        if ($viewer === null || ! filled($this->original_path)) {
            return null;
        }

        $this->loadMissing('listing');
        if ((int) $this->listing?->user_id !== (int) $viewer->id) {
            return null;
        }

        return $this->ownerPreviewUrl();
    }

    public function ownerPreviewUrl(): ?string
    {
        if (! filled($this->original_path) || $this->listing_id === null || $this->id === null) {
            return null;
        }

        $path = URL::temporarySignedRoute(
            'my.listings.media.preview',
            now()->addHours(6),
            ['listing' => $this->listing_id, 'media' => $this->id],
            absolute: false,
        );

        $request = request();
        if ($request && filled($request->getHttpHost())) {
            return $request->getSchemeAndHttpHost().$path;
        }

        return rtrim((string) config('app.url'), '/').$path;
    }
}
