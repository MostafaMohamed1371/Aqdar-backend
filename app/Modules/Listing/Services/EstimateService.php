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
        abort_unless((int) $listing->user_id === (int) $viewer->id, 404);

        $listing->loadMissing(['financials', 'area', 'governorate', 'unitType']);
        $financials = $listing->financials;
        $inputs = $this->inputs($financials);
        $percent = $this->cancellationPercent($financials);
        $paid = $this->money($financials, 'total_paid');
        $asking = $listing->publicAskingPrice();
        $askingValue = $asking !== null ? (float) $asking : null;
        $computable = $percent !== null;

        return [
            'listing_id' => $listing->id,
            'public_code' => $listing->public_code,
            'computable' => $computable,
            'reason' => $computable ? null : 'cancellation_percent_missing',
            'inputs' => $inputs,
            'cancellation' => $computable ? $this->cancellation($percent, $paid, $financials) : null,
            'transfer' => [
                'asking_price' => $askingValue,
                'paid' => $paid,
                'note' => 'paid_plus_agreed_difference',
            ],
            'market' => $this->market($listing),
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

    /** @return array<string, mixed> */
    private function cancellation(float $percent, ?float $paid, ?ListingFinancial $financials): array
    {
        $penalty = $paid === null ? null : round($paid * ($percent / 100), 2);
        $recovery = $paid === null || $penalty === null ? null : round($paid - $penalty, 2);

        return [
            'percent' => $percent,
            'percent_source' => ($financials?->cancellation_percent_verified && $financials->verified_cancellation_percent !== null)
                ? 'verified'
                : 'declared',
            'penalty' => $penalty,
            'net_recovery' => $recovery,
        ];
    }

    /** @return array<string, mixed> */
    private function market(Listing $listing): array
    {
        $prices = Listing::query()
            ->whereIn('status', ListingStatus::publicCases())
            ->where('id', '!=', $listing->id)
            ->when($listing->area_id, fn ($query) => $query->where('area_id', $listing->area_id))
            ->when($listing->unit_type_id, fn ($query) => $query->where('unit_type_id', $listing->unit_type_id))
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
