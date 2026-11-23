<?php

namespace App\Modules\Dashboard\Services;

use App\Core\Enums\BuyerRequestStatus;
use App\Core\Enums\CommissionStatus;
use App\Core\Enums\LeadStatus;
use App\Modules\Finance\Models\Commission;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only analytics for ADM-12 (FR-073): commission ledger, sales funnel, and a transparent run-rate forecast.
 * Everything is derived from existing rows; nothing here moves money or promises revenue.
 */
class ReportAnalyticsService
{
    /** Statuses that count as booked revenue. Waived and reversed commissions never do. */
    private const REVENUE_STATUSES = [CommissionStatus::Earned, CommissionStatus::Due, CommissionStatus::Paid];

    /** Statuses still waiting to be collected. */
    private const RECEIVABLE_STATUSES = [CommissionStatus::Earned, CommissionStatus::Due];

    public const FORECAST_LOOKBACK_MONTHS = 3;

    public const FORECAST_HORIZON_MONTHS = 3;

    public function __construct(private ReportService $reports) {}

    /**
     * Lead and buyer-request counts per stage for the selected filters, with each stage's share of all records in the range.
     *
     * @param  array<string, mixed>  $filters
     * @return array{leads: list<array{key: string, count: int, percent: float}>, requests: list<array{key: string, count: int, percent: float}>}
     */
    public function funnel(array $filters): array
    {
        $leadBase = $this->reports->leadQuery(array_merge($filters, ['status' => 'all']));
        $requestBase = $this->reports->requestQuery(array_merge($filters, ['status' => 'all']));

        $leads = collect(LeadStatus::cases())->mapWithKeys(
            fn (LeadStatus $status) => [$status->value => (clone $leadBase)->where('status', $status->value)->count()],
        );
        $requests = collect(BuyerRequestStatus::cases())->mapWithKeys(
            fn (BuyerRequestStatus $status) => [$status->value => (clone $requestBase)->where('status', $status->value)->count()],
        );

        return [
            'leads' => $this->stages($leads),
            'requests' => $this->stages($requests),
        ];
    }

    /**
     * Commission ledger for the selected range, grouped by status and by month.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     by_status: list<array{status: string, count: int, amount: float, tax_amount: float}>,
     *     months: list<array{key: string, count: int, amount: float, tax_amount: float, net: float}>,
     *     net_revenue: float,
     *     receivable: float
     * }
     */
    public function ledger(array $filters): array
    {
        [$start, $end] = $this->reports->range($filters);
        $rows = $this->commissions($start, $end);

        $byStatus = collect(CommissionStatus::cases())->map(function (CommissionStatus $status) use ($rows): array {
            $matching = $rows->filter(fn (Commission $commission) => $commission->status === $status);

            return [
                'status' => $status->value,
                'count' => $matching->count(),
                'amount' => $this->sum($matching, 'amount'),
                'tax_amount' => $this->sum($matching, 'tax_amount'),
            ];
        })->values()->all();

        $months = $rows
            ->groupBy(fn (Commission $commission) => $commission->created_at->format('Y-m'))
            ->sortKeys()
            ->map(fn (Collection $group, string $key): array => [
                'key' => $key,
                'count' => $group->count(),
                'amount' => $this->sum($group, 'amount'),
                'tax_amount' => $this->sum($group, 'tax_amount'),
                'net' => $this->sum($this->revenueRows($group), 'amount'),
            ])
            ->values()
            ->all();

        return [
            'by_status' => $byStatus,
            'months' => $months,
            'net_revenue' => $this->sum($this->revenueRows($rows), 'amount'),
            'receivable' => $this->receivable(),
        ];
    }

    /**
     * Flat run-rate forecast: the average booked revenue of the last full months, carried forward.
     * It is an estimate for planning, never a commitment, and says so with its inputs.
     *
     * @return array{
     *     basis: list<array{key: string, net: float}>,
     *     run_rate: float,
     *     projection: list<array{key: string, amount: float}>,
     *     receivable: float,
     *     enough_history: bool
     * }
     */
    public function forecast(?CarbonInterface $asOf = null): array
    {
        $asOf = $asOf !== null ? Carbon::instance($asOf) : now();
        $firstOfThisMonth = $asOf->copy()->startOfMonth();
        $windowStart = $firstOfThisMonth->copy()->subMonths(self::FORECAST_LOOKBACK_MONTHS);

        $rows = $this->revenueRows($this->commissions($windowStart, $firstOfThisMonth->copy()->subSecond()));

        $basis = collect(range(self::FORECAST_LOOKBACK_MONTHS, 1))->map(function (int $back) use ($firstOfThisMonth, $rows): array {
            $month = $firstOfThisMonth->copy()->subMonths($back);
            $inMonth = $rows->filter(fn (Commission $commission) => $commission->created_at->format('Y-m') === $month->format('Y-m'));

            return ['key' => $month->format('Y-m'), 'net' => $this->sum($inMonth, 'amount')];
        })->all();

        $runRate = round(array_sum(array_column($basis, 'net')) / self::FORECAST_LOOKBACK_MONTHS, 2);

        $projection = collect(range(0, self::FORECAST_HORIZON_MONTHS - 1))
            ->map(fn (int $ahead): array => [
                'key' => $firstOfThisMonth->copy()->addMonths($ahead)->format('Y-m'),
                'amount' => $runRate,
            ])
            ->all();

        return [
            'basis' => $basis,
            'run_rate' => $runRate,
            'projection' => $projection,
            'receivable' => $this->receivable(),
            'enough_history' => collect($basis)->contains(fn (array $month) => $month['net'] > 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<string>, 1: list<list<string|null>>}
     */
    public function ledgerTable(array $filters): array
    {
        [$start, $end] = $this->reports->range($filters);

        $rows = $this->commissions($start, $end)
            ->groupBy(fn (Commission $commission) => $commission->created_at->format('Y-m').'|'.$commission->status?->value)
            ->sortKeys()
            ->map(function (Collection $group, string $key): array {
                [$month, $status] = explode('|', $key);

                return [
                    $month,
                    $status,
                    (string) $group->count(),
                    number_format($this->sum($group, 'amount'), 2, '.', ''),
                    number_format($this->sum($group, 'tax_amount'), 2, '.', ''),
                ];
            })
            ->values()
            ->all();

        return [['month', 'status', 'count', 'amount', 'tax_amount'], $rows];
    }

    /** @return Collection<int, Commission> */
    private function commissions(CarbonInterface $start, CarbonInterface $end): Collection
    {
        return Commission::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'status', 'amount', 'tax_amount', 'created_at']);
    }

    /**
     * @param  Collection<int, Commission>  $rows
     * @return Collection<int, Commission>
     */
    private function revenueRows(Collection $rows): Collection
    {
        return $rows->filter(fn (Commission $commission) => in_array($commission->status, self::REVENUE_STATUSES, true));
    }

    private function receivable(): float
    {
        return round((float) Commission::query()
            ->whereIn('status', array_map(fn (CommissionStatus $status) => $status->value, self::RECEIVABLE_STATUSES))
            ->sum('amount'), 2);
    }

    /** @param Collection<int, Commission> $rows */
    private function sum(Collection $rows, string $column): float
    {
        return round((float) $rows->sum(fn (Commission $commission) => (float) $commission->{$column}), 2);
    }

    /**
     * @param  Collection<string, int>  $counts
     * @return list<array{key: string, count: int, percent: float}>
     */
    private function stages(Collection $counts): array
    {
        $total = (int) $counts->sum();

        return $counts->map(fn (int $count, string $key): array => [
            'key' => $key,
            'count' => $count,
            'percent' => $total > 0 ? round($count * 100 / $total, 1) : 0.0,
        ])->values()->all();
    }
}
