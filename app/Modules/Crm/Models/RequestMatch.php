<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\MatchBuyerResponse;
use App\Modules\Crm\Database\Factories\RequestMatchFactory;
use App\Modules\Listing\Models\Listing;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'buyer_request_id',
    'listing_id',
    'score',
    'sent_at',
    'buyer_response',
])]
class RequestMatch extends Model
{
    /** @use HasFactory<RequestMatchFactory> */
    use HasFactory;

    protected static function newFactory(): RequestMatchFactory
    {
        return RequestMatchFactory::new();
    }

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'sent_at' => 'datetime',
            'buyer_response' => MatchBuyerResponse::class,
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BuyerRequest::class, 'buyer_request_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
