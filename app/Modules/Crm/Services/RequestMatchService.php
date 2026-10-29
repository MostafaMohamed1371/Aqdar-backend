<?php

namespace App\Modules\Crm\Services;

use App\Core\Enums\BuyerRequestStatus;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\SegmentPreference;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\RequestMatch;
use App\Modules\Listing\Models\Listing;

class RequestMatchService
{
    public function rematch(BuyerRequest $request): void
    {
        $listings = Listing::query()
            ->whereIn('status', ListingStatus::publicCases())
            ->with('financials')
            ->get();

        foreach ($listings as $listing) {
            $this->upsert($request, $listing);
        }
    }

    public function indexListing(Listing $listing): void
    {
        if (! $listing->isPubliclyVisible()) {
            return;
        }

        $listing->loadMissing('financials');

        BuyerRequest::query()
            ->whereNotIn('status', [
                BuyerRequestStatus::Converted->value,
                BuyerRequestStatus::ClosedNoMatch->value,
            ])
            ->orderBy('id')
            ->each(function (BuyerRequest $request) use ($listing): void {
                $this->upsert($request, $listing);
            });
    }

    private function upsert(BuyerRequest $request, Listing $listing): void
    {
        $score = $this->score($request, $listing);
        if ($score < 40) {
            return;
        }

        RequestMatch::query()->updateOrCreate(
            [
                'buyer_request_id' => $request->id,
                'listing_id' => $listing->id,
            ],
            ['score' => $score],
        );
    }

    public function score(BuyerRequest $request, Listing $listing): int
    {
        $points = 0;
        $max = 0;

        $this->accumulate($max, $points, $request->unit_type_id, (int) $listing->unit_type_id === (int) $request->unit_type_id, 20);
        $this->accumulate($max, $points, $request->governorate_id, (int) $listing->governorate_id === (int) $request->governorate_id, 15);
        $this->accumulate($max, $points, $request->area_id, (int) $listing->area_id === (int) $request->area_id, 15);
        $this->accumulate($max, $points, $request->developer_id, (int) $listing->developer_id === (int) $request->developer_id, 10);
        $this->accumulate($max, $points, $request->project_id, (int) $listing->project_id === (int) $request->project_id, 10);

        if ($request->bedrooms !== null) {
            $max += 10;
            $needed = (int) $request->bedrooms >= 5 ? 5 : (int) $request->bedrooms;
            if ((int) $listing->bedrooms >= $needed) {
                $points += 10;
            }
        }

        if ($request->area_sqm_min !== null || $request->area_sqm_max !== null) {
            $max += 10;
            $sqm = $listing->area_sqm !== null ? (float) $listing->area_sqm : null;
            if ($sqm !== null
                && ($request->area_sqm_min === null || $sqm >= (float) $request->area_sqm_min)
                && ($request->area_sqm_max === null || $sqm <= (float) $request->area_sqm_max)) {
                $points += 10;
            }
        }

        $price = $listing->publicAskingPrice();
        if ($request->budget_min !== null || $request->budget_max !== null) {
            $max += 15;
            if ($price !== null
                && ($request->budget_min === null || (float) $price >= (float) $request->budget_min)
                && ($request->budget_max === null || (float) $price <= (float) $request->budget_max)) {
                $points += 15;
            }
        }

        if ($request->segment_preference !== null && $request->segment_preference !== SegmentPreference::Any) {
            $max += 10;
            if ($listing->segment?->value === $request->segment_preference->value) {
                $points += 10;
            }
        }

        if ($request->finishing && $request->finishing !== 'any') {
            $max += 5;
            if ((string) $listing->finishing === (string) $request->finishing) {
                $points += 5;
            }
        }

        return $max === 0 ? 0 : (int) round(($points / $max) * 100);
    }

    private function accumulate(int &$max, int &$points, mixed $criterion, bool $matched, int $weight): void
    {
        if ($criterion === null || $criterion === '') {
            return;
        }
        $max += $weight;
        if ($matched) {
            $points += $weight;
        }
    }
}
