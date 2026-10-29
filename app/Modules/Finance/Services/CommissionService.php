<?php

namespace App\Modules\Finance\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\CaseStatus;
use App\Core\Enums\CommissionBasis;
use App\Core\Enums\CommissionPayer;
use App\Core\Enums\CommissionStatus;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Finance\Models\Commission;
use App\Modules\Setting\Services\SettingService;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommissionService
{
    public function __construct(
        private AuditLogger $audit,
        private SettingService $settings,
    ) {}

    public function paginate(StaffUser $staff, array $filters): LengthAwarePaginator
    {
        $this->assertCanEdit($staff);

        return $this->filtered($filters)
            ->with(['transferCase.buyer', 'listing.unitType', 'listing.area', 'creator', 'history.actor'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
    }

    /** @return Collection<int, TransferCase> */
    public function availableCases(): Collection
    {
        return TransferCase::query()
            ->where('status', CaseStatus::Completed->value)
            ->whereDoesntHave('commission')
            ->with(['listing.unitType', 'listing.area'])
            ->latest('id')
            ->limit(100)
            ->get();
    }

    /** @return array<string, mixed> */
    public function counts(StaffUser $staff, array $filters = []): array
    {
        $this->assertCanEdit($staff);
        $base = $this->filtered($filters);

        return [
            'total_amount' => (float) (clone $base)->where('status', '!=', CommissionStatus::Waived->value)->sum('amount'),
            'due_amount' => (float) (clone $base)->where('status', CommissionStatus::Due->value)->sum('amount'),
            'deal_count' => (clone $base)->count(),
            'average_rate' => (float) (clone $base)->where('basis', CommissionBasis::Percent->value)->avg('rate'),
        ];
    }

    /** @return array<string, mixed> */
    public function charts(StaffUser $staff, array $filters = []): array
    {
        $this->assertCanEdit($staff);
        $anchor = $this->monthStart((string) ($filters['month'] ?? '')) ?? now()->startOfMonth();
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = $anchor->copy()->subMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query = Commission::query()
                ->where('status', '!=', CommissionStatus::Waived->value)
                ->whereBetween('created_at', [$start, $end]);
            $months[] = [
                'key' => $start->format('Y-m'),
                'label' => __('aqdar.commissions.month_name.'.(int) $start->format('n')),
                'revenue' => (float) (clone $query)->sum('sale_value'),
                'commission' => (float) (clone $query)->sum('amount'),
            ];
        }

        $typeQuery = $this->filtered([
            'month' => (string) ($filters['month'] ?? ''),
            'status' => 'all',
            'q' => '',
        ])->where('status', '!=', CommissionStatus::Waived->value)->with('listing.unitType');

        $buckets = ['apartment' => 0.0, 'villa' => 0.0, 'land' => 0.0, 'investment' => 0.0];
        foreach ($typeQuery->get() as $row) {
            $buckets[$this->typeBucket($row->listing?->unitType?->slug)] += (float) ($row->sale_value ?? 0);
        }
        $total = array_sum($buckets);
        $colors = [
            'apartment' => '#087e82',
            'villa' => '#14283f',
            'land' => '#c5985f',
            'investment' => '#9fb0c2',
        ];
        $types = [];
        foreach ($buckets as $key => $value) {
            $percent = $total > 0 ? (int) round(($value / $total) * 100) : 0;
            $types[] = [
                'key' => $key,
                'label' => __('aqdar.requests.type.'.$key),
                'value' => $value,
                'percent' => $percent,
                'color' => $colors[$key],
            ];
        }

        return [
            'months' => $months,
            'types' => $types,
            'revenue_total' => $total,
            'revenue_label' => $this->compactMoney($total),
        ];
    }

    /** @return array<string, string> */
    public function monthOptions(): array
    {
        $options = [];
        $cursor = now()->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $date = $cursor->copy()->subMonths($i);
            $options[$date->format('Y-m')] = __('aqdar.commissions.month_name.'.(int) $date->format('n')).' '.$date->format('Y');
        }

        return $options;
    }

    public function recordedTotal(): float
    {
        return (float) Commission::query()
            ->whereIn('status', [CommissionStatus::Earned->value, CommissionStatus::Due->value, CommissionStatus::Paid->value])
            ->sum('amount');
    }

    public function create(StaffUser $staff, array $data): Commission
    {
        $this->assertCanEdit($staff);
        $case = TransferCase::query()->with('listing.financials')->findOrFail((int) $data['case_id']);
        if ($case->status !== CaseStatus::Completed) {
            throw ValidationException::withMessages([
                'case_id' => [__('aqdar.commissions.case_must_be_closed')],
            ]);
        }
        if (Commission::query()->where('case_id', $case->id)->exists()) {
            throw ValidationException::withMessages([
                'case_id' => [__('aqdar.commissions.already_exists')],
            ]);
        }

        $payload = $this->normalize($data, $case);
        $commission = Commission::query()->create($payload + [
            'case_id' => $case->id,
            'listing_id' => $case->listing_id,
            'created_by' => $staff->id,
        ]);
        $this->recordHistory($commission, $staff, null, $commission->status, $data['reason'] ?? __('aqdar.commissions.opened'), [], $commission->snapshot());
        $this->audit->write('commission.created', $staff, $commission, $commission->snapshot());

        return $commission->fresh(['transferCase', 'listing']) ?? $commission;
    }

    public function update(Commission $commission, StaffUser $staff, array $data): Commission
    {
        $this->assertCanEdit($staff);
        $before = $commission->snapshot();
        $payload = $this->normalize($data, $commission->transferCase, $commission);
        $old = $commission->status;
        $commission->forceFill($payload)->save();
        $fresh = $commission->fresh() ?? $commission;
        $this->recordHistory(
            $fresh,
            $staff,
            $old,
            $fresh->status,
            $data['reason'],
            $before,
            $fresh->snapshot(),
        );
        $this->audit->write('commission.updated', $staff, $fresh, [
            'reason' => $data['reason'],
            'before' => $before,
            'after' => $fresh->snapshot(),
        ]);

        return $fresh;
    }

    public function exportCsv(StaffUser $staff, array $filters): StreamedResponse
    {
        $this->assertCanEdit($staff);
        abort_unless($staff->can('export_csv'), 403, __('aqdar.http.forbidden'));

        $rows = $this->filtered($filters)
            ->with(['transferCase', 'listing'])
            ->orderBy('id')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'date', 'case', 'payer', 'listing', 'sale_value', 'basis', 'rate', 'amount', 'invoice', 'status',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    optional($row->created_at)?->toDateString(),
                    $row->transferCase?->public_code,
                    $row->payer_party?->value,
                    $row->listing?->public_code,
                    $row->sale_value,
                    $row->basis?->value,
                    $row->rate,
                    $row->amount,
                    $row->invoice_reference,
                    $row->status?->value,
                ]);
            }
            fclose($out);
        }, 'commissions-'.now()->toDateString().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function latestChange(): ?object
    {
        return \App\Modules\Finance\Models\CommissionHistory::query()
            ->with(['actor', 'commission.transferCase'])
            ->latest('id')
            ->first();
    }

    public function defaultRate(): float
    {
        $setting = $this->settings->findByKey('commission_default_rate');

        return (float) ($setting?->value ?? config('aqdar.commission.default_rate', '2.5'));
    }

    public function taxPercent(): ?float
    {
        $setting = $this->settings->findByKey('commission_tax_percent');
        if ($setting === null || $setting->value === null || $setting->value === '') {
            $fallback = config('aqdar.commission.tax_percent');

            return $fallback === null || $fallback === '' ? null : (float) $fallback;
        }

        return (float) $setting->value;
    }

    public function assertCanEdit(StaffUser $staff): void
    {
        if ($staff->can('edit_commissions')) {
            return;
        }
        $this->audit->write('permission_denied', $staff, null, [
            'permission' => 'edit_commissions',
            'path' => request()->path(),
        ]);
        abort(403, __('aqdar.http.forbidden'));
    }

    /** @param array<string, mixed> $filters */
    private function filtered(array $filters): Builder
    {
        $query = Commission::query();
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }
        if (! empty($filters['q'])) {
            $like = '%'.addcslashes((string) $filters['q'], '%_\\').'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('invoice_reference', 'ilike', $like)
                    ->orWhereHas('transferCase', fn ($case) => $case->where('public_code', 'ilike', $like));
            });
        }
        $month = $this->monthStart((string) ($filters['month'] ?? ''));
        if ($month !== null) {
            $query->whereBetween('created_at', [$month, $month->copy()->endOfMonth()]);
        }

        return $query;
    }

    private function monthStart(string $month): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $month.'-01')?->startOfMonth();
    }

    private function compactMoney(float $value): string
    {
        if ($value >= 1_000_000) {
            return rtrim(rtrim(number_format($value / 1_000_000, 2, '.', ''), '0'), '.').'M';
        }
        if ($value >= 1_000) {
            return rtrim(rtrim(number_format($value / 1_000, 1, '.', ''), '0'), '.').'K';
        }

        return number_format($value);
    }

    private function typeBucket(?string $slug): string
    {
        $slug = strtolower((string) $slug);
        if (str_contains($slug, 'land')) {
            return 'land';
        }
        if (str_contains($slug, 'villa') || str_contains($slug, 'townhouse') || str_contains($slug, 'chalet')) {
            return 'villa';
        }
        if (str_contains($slug, 'office') || str_contains($slug, 'shop') || str_contains($slug, 'invest')) {
            return 'investment';
        }

        return 'apartment';
    }

    /** @param array<string, mixed> $data */
    private function normalize(array $data, ?TransferCase $case, ?Commission $existing = null): array
    {
        $basis = CommissionBasis::from((string) ($data['basis'] ?? $existing?->basis?->value ?? CommissionBasis::Percent->value));
        $sale = isset($data['sale_value']) && $data['sale_value'] !== ''
            ? (float) $data['sale_value']
            : ($existing?->sale_value !== null ? (float) $existing->sale_value : $this->saleValue($case));
        $rate = $basis === CommissionBasis::Percent
            ? (float) ($data['rate'] ?? $existing?->rate ?? $this->defaultRate())
            : null;
        if ($basis === CommissionBasis::Percent) {
            $amount = round(($sale ?? 0) * (($rate ?? 0) / 100), 2);
        } else {
            $amount = (float) ($data['amount'] ?? $existing?->amount ?? 0);
        }
        $taxPercent = $this->taxPercent();
        $tax = $taxPercent === null ? null : round($amount * ($taxPercent / 100), 2);

        return [
            'payer_party' => CommissionPayer::from((string) ($data['payer_party'] ?? $existing?->payer_party?->value ?? CommissionPayer::Buyer->value)),
            'basis' => $basis,
            'rate' => $rate,
            'sale_value' => $sale,
            'amount' => $amount,
            'tax_amount' => $tax,
            'invoice_reference' => $data['invoice_reference'] ?? $existing?->invoice_reference,
            'status' => CommissionStatus::from((string) ($data['status'] ?? $existing?->status?->value ?? $this->defaultStatus())),
            'note' => $data['note'] ?? $existing?->note,
        ];
    }

    private function saleValue(?TransferCase $case): ?float
    {
        $price = $case?->listing?->publicAskingPrice();

        return $price !== null ? (float) $price : null;
    }

    private function defaultStatus(): string
    {
        $setting = $this->settings->findByKey('commission_default_status');

        return (string) ($setting?->value ?? config('aqdar.commission.default_status', 'earned'));
    }

    private function recordHistory(
        Commission $commission,
        StaffUser $actor,
        ?CommissionStatus $old,
        CommissionStatus $new,
        string $reason,
        array $before,
        array $after,
    ): void {
        $commission->history()->create([
            'actor_id' => $actor->id,
            'old_status' => $old?->value,
            'new_status' => $new->value,
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
