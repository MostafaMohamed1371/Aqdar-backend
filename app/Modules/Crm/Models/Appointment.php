<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\AppointmentStatus;
use App\Modules\Crm\Database\Factories\AppointmentFactory;
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
    'requested_date',
    'requested_slot',
    'notes',
    'status',
    'consent_at',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected static function newFactory(): AppointmentFactory
    {
        return AppointmentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'status' => AppointmentStatus::class,
            'consent_at' => 'datetime',
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
