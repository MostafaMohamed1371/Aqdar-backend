<?php

namespace App\Modules\Listing\Http\Resources;

use App\Modules\Listing\Http\Resources\Concerns\MapsCatalog;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingFinancial;
use App\Modules\Setting\Services\SettingService;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class PublicListingResource extends JsonResource
{
    use MapsCatalog;

    public function toArray(Request $request): array
    {
        $financials = $this->financials;
        $user = $request->user();

        return [
            'id' => $this->id,
            'public_code' => $this->public_code,
            'share_url' => url('/property/'.$this->public_code),
            'title' => $this->localizedTitle(),
            'description' => $this->localizedDescription(),
            'status' => $this->status?->value,
            'segment' => $this->segment?->value,
            'distressed_child' => $this->distressed_child?->value,
            'category' => $this->named($this->category),
            'governorate' => $this->named($this->governorate),
            'area' => $this->named($this->area),
            'developer' => $this->named($this->developer),
            'project' => $this->named($this->project),
            'unit_type' => $this->named($this->unitType),
            'area_sqm' => $this->area_sqm !== null ? (float) $this->area_sqm : null,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'floor' => $this->floor,
            'finishing' => $this->finishing,
            'furnished' => $this->furnished,
            'handover_date' => $this->handover_date?->toDateString(),
            'neighborhood' => $this->neighborhood,
            'phase' => $this->phase,
            'parking' => $this->parking,
            'offer_expires_at' => $this->offer_expires_at?->toIso8601String(),
            'amenities' => $this->namedAmenities($this->amenity_ids),
            'tags' => $this->namedTags($this->tag_ids),
            'asking_price' => $this->publicAskingPrice() !== null ? (float) $this->publicAskingPrice() : null,
            'asking_price_source' => $this->askingPriceSource(),
            'currency' => 'EGP',
            'financial_summary' => [
                'contract_price' => $this->labelled($financials, 'contract_price'),
                'total_paid' => $this->labelled($financials, 'total_paid'),
                'remaining' => $this->labelled($financials, 'remaining'),
                'overdue_count' => $this->labelled($financials, 'overdue_count'),
                'overdue_amount' => $this->labelled($financials, 'overdue_amount'),
                'next_due_date' => $this->labelled($financials, 'next_due_date'),
                'installment_amount' => $this->labelled($financials, 'installment_amount'),
                'remaining_installments' => $this->labelled($financials, 'remaining_installments'),
            ],
            'verification_badge' => (bool) $financials?->asking_price_verified,
            'photos' => $this->approvedPhotos(),
            'is_favorited' => in_array((int) $this->id, $this->favoriteListingIds($user), true),
            'published_at' => $this->published_at,
            'sales_contact' => app(SettingService::class)->publicConfig()['sales_contact'],
        ];
    }

    private function approvedPhotos(): array
    {
        if (! $this->relationLoaded('media')) {
            return [];
        }

        return $this->media
            ->filter(fn ($media) => $media->isApproved())
            ->map(fn ($media) => [
                'id' => $media->id,
                'sort_order' => $media->sort_order,
                'url' => $media->publicUrl(),
                'mime' => $media->mime,
                'width' => $media->width,
                'height' => $media->height,
            ])
            ->values()
            ->all();
    }

    /** @return array{value: mixed, source: string}|null */
    private function labelled(?ListingFinancial $financials, string $field): ?array
    {
        if ($financials === null) {
            return null;
        }

        $value = $financials->publicValue($field);

        if ($value === null) {
            return null;
        }

        $verified = (bool) $financials->{$field.'_verified'} && $financials->{'verified_'.$field} !== null;

        if ($field === 'next_due_date') {
            $value = $value instanceof CarbonInterface ? $value->toDateString() : (string) $value;
        } elseif (in_array($field, ['overdue_count', 'remaining_installments'], true)) {
            $value = (int) $value;
        } else {
            $value = (float) $value;
        }

        return [
            'value' => $value,
            'source' => $verified ? 'verified' : 'declared',
        ];
    }
}
