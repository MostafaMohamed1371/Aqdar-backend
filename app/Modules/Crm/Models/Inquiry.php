<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\ContactMethod;
use App\Core\Enums\InquiryStatus;
use App\Core\Enums\PreferredTime;
use App\Modules\Crm\Database\Factories\InquiryFactory;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'public_code',
    'user_id',
    'listing_id',
    'lead_id',
    'message',
    'inquiry_type',
    'contact_method',
    'preferred_time',
    'consent_at',
    'status',
])]
class Inquiry extends Model
{
    /** @use HasFactory<InquiryFactory> */
    use HasFactory;

    protected static function newFactory(): InquiryFactory
    {
        return InquiryFactory::new();
    }

    protected function casts(): array
    {
        return [
            'contact_method' => ContactMethod::class,
            'preferred_time' => PreferredTime::class,
            'consent_at' => 'datetime',
            'status' => InquiryStatus::class,
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
