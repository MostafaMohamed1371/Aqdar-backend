<?php

namespace App\Modules\Listing\Services;

use App\Core\Enums\ListingStatus;
use App\Modules\Catalog\Models\ContentBlock;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Models\ListingFinancial;
use App\Modules\User\Models\User;

class EstimateService
{
    /** @return array<string, mixed> */
    public function forListing(Listing $listing, User $viewer): array
    {
        $isOwner = (int) $listing->user_id === (int) $viewer->id;
        abort_unless($isOwner || $listing->isPubliclyVisible(), 404);

        $listing->loadMissing(['financials', 'area', 'governorate', 'unitType']);
        if (! $isOwner) {
            return $this->forBuyer($listing);
        }
        $financials = $listing->financials;
        $percent = $this->cancellationPercent($financials);
        $paid = $this->money($financials, 'total_paid');
        $asking = $listing->publicAskingPrice();
        $askingValue = $asking !== null ? (float) $asking : null;

        return $this->payload(
            source: 'listing',
            listingId: $listing->id,
            publicCode: $listing->public_code,
            inputs: $this->inputs($financials),
            percent: $percent,
            paid: $paid,
            asking: $askingValue,
            percentSource: $this->listingPercentSource($financials),
            market: $this->market($listing->area_id, $listing->unit_type_id, $listing->id),
        ) + ['viewer' => 'owner'];
    }

    /**
     * Buyers see only the figures already public on the listing; the seller's contractual
     * cancellation percentage stays private, so the cancellation branch is never computed.
     *
     * @return array<string, mixed>
     */
    private function forBuyer(Listing $listing): array
    {
        $financials = $listing->financials;
        $asking = $listing->publicAskingPrice();
        $payload = $this->payload(
            source: 'listing',
            listingId: $listing->id,
            publicCode: $listing->public_code,
            inputs: [
                $this->input($financials, 'contract_price'),
                $this->input($financials, 'total_paid'),
                $this->input($financials, 'remaining'),
                $this->input($financials, 'asking_price'),
            ],
            percent: null,
            paid: $this->money($financials, 'total_paid'),
            asking: $asking !== null ? (float) $asking : null,
            percentSource: 'declared',
            market: $this->market($listing->area_id, $listing->unit_type_id, $listing->id),
        );
        $payload['viewer'] = 'buyer';
        $payload['reason'] = 'cancellation_terms_private';

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function fromManual(User $viewer, array $data): array
    {
        $listing = $this->ownedListing($viewer, $data['listing_id'] ?? null);
        $values = [
            'contract_price' => $this->nullableFloat($data['contract_price'] ?? null),
            'total_paid' => $this->nullableFloat($data['total_paid'] ?? null),
            'remaining' => $this->nullableFloat($data['remaining'] ?? null),
            'cancellation_percent' => $this->nullableFloat($data['cancellation_percent'] ?? null),
            'asking_price' => $this->nullableFloat($data['asking_price'] ?? null),
        ];

        $areaId = array_key_exists('area_id', $data) && $data['area_id'] !== null
            ? (int) $data['area_id']
            : $listing?->area_id;
        $unitTypeId = array_key_exists('unit_type_id', $data) && $data['unit_type_id'] !== null
            ? (int) $data['unit_type_id']
            : $listing?->unit_type_id;

        return $this->payload(
            source: 'manual',
            listingId: $listing?->id,
            publicCode: $listing?->public_code,
            inputs: $this->manualInputs($values),
            percent: $values['cancellation_percent'],
            paid: $values['total_paid'],
            asking: $values['asking_price'],
            percentSource: 'manual',
            market: $this->market($areaId, $unitTypeId, $listing?->id),
        );
    }

    /**
     * @param  list<array{key: string, value: float|null, verified: bool, required?: bool}>  $inputs
     * @param  array<string, mixed>  $market
     * @return array<string, mixed>
     */
    private function payload(
        string $source,
        ?int $listingId,
        ?string $publicCode,
        array $inputs,
        ?float $percent,
        ?float $paid,
        ?float $asking,
        string $percentSource,
        array $market,
    ): array {
        $computable = $percent !== null;

        return [
            'listing_id' => $listingId,
            'public_code' => $publicCode,
            'source' => $source,
            'computable' => $computable,
            'reason' => $computable ? null : 'cancellation_percent_missing',
            'inputs' => $inputs,
            'cancellation' => $computable ? $this->cancellation($percent, $paid, $percentSource) : null,
            'transfer' => [
                'asking_price' => $asking,
                'paid' => $paid,
                'note' => 'paid_plus_agreed_difference',
            ],
            'market' => $market,
            'assumptions' => [
                'comparables_same_area',
                'no_invented_penalty',
                'developer_transfer_fees_excluded',
            ],
            'disclaimer' => $this->disclaimer(),
        ];
    }

    /** @return list<array{key: string, value: float|null, verified: bool, required?: bool}> */
    private function inputs(?ListingFinancial $financials): array
    {
        return [
            $this->input($financials, 'contract_price'),
            $this->input($financials, 'total_paid'),
            $this->input($financials, 'remaining'),
            $this->input($financials, 'cancellation_percent', required: true),
            $this->input($financials, 'asking_price'),
        ];
    }

    /**
     * @param  array<string, float|null>  $values
     * @return list<array{key: string, value: float|null, verified: bool, required?: bool}>
     */
    private function manualInputs(array $values): array
    {
        $rows = [];
        foreach (['contract_price', 'total_paid', 'remaining', 'cancellation_percent', 'asking_price'] as $key) {
            $row = [
                'key' => $key,
                'value' => $values[$key],
                'verified' => false,
            ];
            if ($key === 'cancellation_percent') {
                $row['required'] = true;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array{key: string, value: float|null, verified: bool, required?: bool} */
    private function input(?ListingFinancial $financials, string $field, bool $required = false): array
    {
        $row = [
            'key' => $field,
            'value' => $this->money($financials, $field),
            'verified' => (bool) ($financials?->{$field.'_verified'} ?? false),
        ];
        if ($required) {
            $row['required'] = true;
        }

        return $row;
    }

    private function money(?ListingFinancial $financials, string $field): ?float
    {
        if ($financials === null) {
            return null;
        }
        $value = $financials->publicValue($field);

        return $value === null || $value === '' ? null : (float) $value;
    }

    private function cancellationPercent(?ListingFinancial $financials): ?float
    {
        return $this->money($financials, 'cancellation_percent');
    }

    private function listingPercentSource(?ListingFinancial $financials): string
    {
        return ($financials?->cancellation_percent_verified && $financials->verified_cancellation_percent !== null)
            ? 'verified'
            : 'declared';
    }

    /** @return array<string, mixed> */
    private function cancellation(float $percent, ?float $paid, string $percentSource): array
    {
        $penalty = $paid === null ? null : round($paid * ($percent / 100), 2);
        $recovery = $paid === null || $penalty === null ? null : round($paid - $penalty, 2);

        return [
            'percent' => $percent,
            'percent_source' => $percentSource,
            'penalty' => $penalty,
            'net_recovery' => $recovery,
        ];
    }

    /** @return array<string, mixed> */
    private function market(?int $areaId, ?int $unitTypeId, ?int $excludeId = null): array
    {
        if ($areaId === null && $unitTypeId === null) {
            return $this->emptyMarket();
        }

        $prices = Listing::query()
            ->whereIn('status', ListingStatus::publicCases())
            ->when($excludeId !== null, fn ($query) => $query->where('id', '!=', $excludeId))
            ->when($areaId !== null, fn ($query) => $query->where('area_id', $areaId))
            ->when($unitTypeId !== null, fn ($query) => $query->where('unit_type_id', $unitTypeId))
            ->with('financials')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(function (Listing $row) {
                $price = $row->publicAskingPrice();

                return $price === null ? null : [
                    'id' => $row->id,
                    'public_code' => $row->public_code,
                    'asking_price' => (float) $price,
                ];
            })
            ->filter()
            ->values();

        $values = $prices->pluck('asking_price')->map(fn ($value) => (float) $value)->all();
        $count = count($values);

        return [
            'label' => 'estimate',
            'certified_valuation' => false,
            'min' => $count > 0 ? min($values) : null,
            'avg' => $count > 0 ? round(array_sum($values) / $count, 2) : null,
            'max' => $count > 0 ? max($values) : null,
            'sample_size' => $count,
            'comparables' => $prices->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyMarket(): array
    {
        return [
            'label' => 'estimate',
            'certified_valuation' => false,
            'min' => null,
            'avg' => null,
            'max' => null,
            'sample_size' => 0,
            'comparables' => [],
        ];
    }

    private function ownedListing(User $viewer, mixed $listingId): ?Listing
    {
        if ($listingId === null || $listingId === '') {
            return null;
        }

        $listing = Listing::query()->find($listingId);
        abort_unless($listing !== null && (int) $listing->user_id === (int) $viewer->id, 404);

        return $listing;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /** @return array{text: string, source: string} */
    private function disclaimer(): array
    {
        $block = ContentBlock::query()
            ->where('key', 'exit_estimate')
            ->where('is_active', true)
            ->first();
        $text = $block?->localizedBody();
        if (is_string($text) && trim($text) !== '') {
            return ['text' => $text, 'source' => 'cms'];
        }

        return ['text' => __('aqdar.estimate.disclaimer'), 'source' => 'fallback'];
    }
}
