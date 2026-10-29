<?php

namespace App\Modules\Dashboard\Services;

use App\Core\Audit\AuditLogger;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\Lead;
use App\Modules\Finance\Models\Commission;
use App\Modules\Listing\Models\Listing;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    /** @var list<string> */
    public const TYPES = ['listings', 'users', 'requests', 'revenue', 'sales'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function kpis(array $filters): array
    {
        [$start, $end] = $this->range($filters);
        [$listingFilters, $requestFilters] = $this->splitStatusFilters($filters);

        return [
            'requests' => $this->requestQuery($requestFilters)->count(),
            'listings' => $this->listingQuery($listingFilters)->count(),
            'users' => User::query()->whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, array{pct: float, down: bool}|null>
     */
    public function deltas(array $filters): array
    {
        $current = $this->kpis($filters);
        [$start, $end] = $this->range($filters);
        $seconds = max(1, $end->getTimestamp() - $start->getTimestamp());
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subSeconds($seconds - 1);
        $previous = $this->kpis([
            'from' => $prevStart->toDateString(),
            'to' => $prevEnd->toDateString(),
        ]);
        $out = [];
        foreach ($current as $key => $value) {
            $before = (float) ($previous[$key] ?? 0);
            $now = (float) $value;
            if ($before <= 0 && $now <= 0) {
                $out[$key] = null;

                continue;
            }
            $pct = $before <= 0 ? 100.0 : round((($now - $before) / $before) * 100, 1);
            $out[$key] = [
                'pct' => abs($pct),
                'down' => $now < $before,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int>
     */
    public function rates(array $filters): array
    {
        [$start, $end] = $this->range($filters);
        $leads = $this->leadQuery($filters);
        $total = (clone $leads)->count();
        $qualified = (clone $leads)->whereNotIn('status', ['new', 'contacted', 'lost'])->count();
        $converted = (clone $leads)->where('status', 'converted')->count();

        return [
            'avg_listing_review_hours' => $this->averageReviewHours($start, $end),
            'first_response_hours' => $this->averageFirstResponseHours($filters),
            'qualified_rate' => $total > 0 ? round($qualified * 100 / $total, 1) : 0,
            'converted_rate' => $total > 0 ? round($converted * 100 / $total, 1) : 0,
            'leads' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{key: string, label: string, listings: int, requests: int, users: int}>
     */
    public function overviewSeries(array $filters): array
    {
        [$start, $end] = $this->range($filters);
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = $end->copy()->startOfMonth()->subMonths($i);
            $from = $month->copy()->startOfMonth();
            $to = $month->copy()->endOfMonth();
            $months[] = [
                'key' => $month->format('Y-m'),
                'label' => $month->isoFormat('MMM'),
                'listings' => Listing::query()->whereBetween('created_at', [$from, $to])->count(),
                'requests' => BuyerRequest::query()->whereBetween('created_at', [$from, $to])->count(),
                'users' => User::query()->whereBetween('created_at', [$from, $to])->count(),
            ];
        }

        return $months;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportCsv(StaffUser $staff, string $type, array $filters, bool $includePhones): StreamedResponse
    {
        abort_unless($staff->can('export_csv'), 403, __('aqdar.http.forbidden'));
        abort_unless(in_array($type, self::TYPES, true), 404);

        if ($includePhones) {
            abort_unless($staff->can('reveal_phone'), 403, __('aqdar.http.forbidden'));
        }

        $this->audit->write(
            $includePhones ? 'report.exported_with_phones' : 'report.exported',
            $staff,
            null,
            ['type' => $type, 'include_phones' => $includePhones],
        );

        [$header, $rows] = $this->table($type, $filters, $includePhones);
        $filename = 'aqdar-'.$type.'-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportPdf(StaffUser $staff, string $type, array $filters, bool $includePhones): Response
    {
        abort_unless($staff->can('export_csv'), 403, __('aqdar.http.forbidden'));
        abort_unless(in_array($type, self::TYPES, true), 404);

        if ($includePhones) {
            abort_unless($staff->can('reveal_phone'), 403, __('aqdar.http.forbidden'));
        }

        $this->audit->write(
            $includePhones ? 'report.exported_with_phones' : 'report.exported',
            $staff,
            null,
            ['type' => $type, 'include_phones' => $includePhones, 'format' => 'pdf'],
        );

        [$header, $rows] = $this->table($type, $filters, $includePhones);
        $pdf = app(ReportPdfWriter::class)->table('AQDAR '.$type, $header, $rows);
        $filename = 'aqdar-'.$type.'-'.now()->toDateString().'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<string>, 1: list<list<string|null>>}
     */
    private function table(string $type, array $filters, bool $includePhones): array
    {
        return match ($type) {
            'listings' => $this->listingTable($filters, $includePhones),
            'users' => $this->userTable($filters, $includePhones),
            'requests' => $this->requestTable($filters, $includePhones),
            'revenue' => $this->revenueTable($filters),
            'sales' => $this->salesTable($filters, $includePhones),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function range(array $filters): array
    {
        $start = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
        $end = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        return [$start, $end];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<string>, 1: list<list<string|null>>}
     */
    private function listingTable(array $filters, bool $includePhones): array
    {
        $header = ['code', 'status', 'segment', 'governorate', 'created_at'];
        if ($includePhones) {
            $header[] = 'owner_phone';
        }
        $rows = [];
        $this->listingQuery($filters)->with(['owner', 'governorate'])->orderBy('id')->chunk(200, function ($chunk) use (&$rows, $includePhones): void {
            foreach ($chunk as $row) {
                $line = [
                    $row->public_code,
                    $row->status?->value,
                    $row->segment?->value,
                    $row->governorate?->name_en ?: $row->governorate?->slug,
                    optional($row->created_at)?->toDateTimeString(),
                ];
                if ($includePhones) {
                    $line[] = $row->owner?->phone;
                }
                $rows[] = $line;
            }
        });

        return [$header, $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<string>, 1: list<list<string|null>>}
     */
    private function userTable(array $filters, bool $includePhones): array
    {
        $header = ['code', 'account_type', 'is_buyer', 'is_seller', 'identity_status', 'created_at'];
        if ($includePhones) {
            $header[] = 'phone';
        }
        [$start, $end] = $this->range($filters);
        $rows = [];
        User::query()->whereBetween('created_at', [$start, $end])->orderBy('id')->chunk(200, function ($chunk) use (&$rows, $includePhones): void {
            foreach ($chunk as $row) {
                $line = [
                    'USR-'.$row->id,
                    $row->account_type?->value ?? $row->account_type,
                    $row->is_buyer ? '1' : '0',
                    $row->is_seller ? '1' : '0',
                    $row->identity_status?->value ?? $row->identity_status,
                    optional($row->created_at)?->toDateTimeString(),
                ];
                if ($includePhones) {
                    $line[] = $row->phone;
                }
                $rows[] = $line;
            }
        });

        return [$header, $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<string>, 1: list<list<string|null>>}
     */
    private function requestTable(array $filters, bool $includePhones): array
    {
        $header = ['code', 'status', 'intent', 'created_at'];
        if ($includePhones) {
            $header[] = 'buyer_phone';
        }
        $rows = [];
        $this->requestQuery($filters)->with('buyer')->orderBy('id')->chunk(200, function ($chunk) use (&$rows, $includePhones): void {
            foreach ($chunk as $row) {
                $line = [
                    $row->public_code,
                    $row->status?->value,
                    $row->intent?->value,
                    optional($row->created_at)?->toDateTimeString(),
                ];
                if ($includePhones) {
                    $line[] = $row->buyer?->phone;
                }
                $rows[] = $line;
            }
        });

        return [$header, $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<string>, 1: list<list<string|null>>}
     */
    private function revenueTable(array $filters): array
    {
        $header = ['date', 'case', 'payer', 'listing', 'sale_value', 'amount', 'status'];
        [$start, $end] = $this->range($filters);
        $rows = [];
        Commission::query()
            ->with(['transferCase', 'listing'])
            ->whereBetween('created_at', [$start, $end])
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy('id')
            ->chunk(200, function ($chunk) use (&$rows): void {
                foreach ($chunk as $row) {
                    $rows[] = [
                        optional($row->created_at)?->toDateString(),
                        $row->transferCase?->public_code,
                        $row->payer_party?->value,
                        $row->listing?->public_code,
                        $row->sale_value,
                        $row->amount,
                        $row->status?->value,
                    ];
                }
            });

        return [$header, $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: list<string>, 1: list<list<string|null>>}
     */
    private function salesTable(array $filters, bool $includePhones): array
    {
        $header = ['code', 'source', 'status', 'assignee_id', 'created_at'];
        if ($includePhones) {
            $header[] = 'buyer_phone';
        }
        $rows = [];
        $this->leadQuery($filters)->with('buyer')->orderBy('id')->chunk(200, function ($chunk) use (&$rows, $includePhones): void {
            foreach ($chunk as $row) {
                $line = [
                    $row->public_code,
                    $row->source?->value,
                    $row->status?->value,
                    $row->assigned_to ? 'STF-'.$row->assigned_to : '',
                    optional($row->created_at)?->toDateTimeString(),
                ];
                if ($includePhones) {
                    $line[] = $row->buyer?->phone;
                }
                $rows[] = $line;
            }
        });

        return [$header, $rows];
    }

    /** @param array<string, mixed> $filters */
    private function listingQuery(array $filters): Builder
    {
        [$start, $end] = $this->range($filters);

        return Listing::query()
            ->whereBetween('created_at', [$start, $end])
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['segment']) && $filters['segment'] !== 'all', fn (Builder $query) => $query->where('segment', $filters['segment']));
    }

    /** @param array<string, mixed> $filters */
    private function requestQuery(array $filters): Builder
    {
        [$start, $end] = $this->range($filters);

        return BuyerRequest::query()
            ->whereBetween('created_at', [$start, $end])
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['owner']), fn (Builder $query) => $query->where('assigned_to', (int) $filters['owner']));
    }

    /** @param array<string, mixed> $filters */
    private function leadQuery(array $filters): Builder
    {
        [$start, $end] = $this->range($filters);

        return Lead::query()
            ->whereBetween('created_at', [$start, $end])
            ->when(! empty($filters['status']) && $filters['status'] !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['source']) && $filters['source'] !== 'all', fn (Builder $query) => $query->where('source', $filters['source']))
            ->when(! empty($filters['owner']), fn (Builder $query) => $query->where('assigned_to', (int) $filters['owner']));
    }

    private function averageReviewHours(CarbonInterface $start, CarbonInterface $end): float
    {
        $hours = Listing::query()
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$start, $end])
            ->whereNotNull('submitted_at')
            ->get(['submitted_at', 'published_at'])
            ->map(fn (Listing $listing) => $listing->submitted_at->diffInHours($listing->published_at))
            ->filter(fn ($h) => $h >= 0);

        return $hours->isEmpty() ? 0.0 : round((float) $hours->avg(), 1);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function splitStatusFilters(array $filters): array
    {
        $status = (string) ($filters['status'] ?? 'all');
        $listingFilters = $filters;
        $requestFilters = $filters;
        $listingStatuses = ['published', 'in_review', 'approved', 'draft', 'submitted', 'resubmitted', 'rejected', 'hold', 'closed'];
        if ($status !== 'all' && in_array($status, $listingStatuses, true)) {
            $requestFilters['status'] = 'all';
        } elseif ($status !== 'all') {
            $listingFilters['status'] = 'all';
        }

        return [$listingFilters, $requestFilters];
    }

    /** @param array<string, mixed> $filters */
    private function averageFirstResponseHours(array $filters): float
    {
        $rows = $this->leadQuery($filters)
            ->where(function (Builder $query): void {
                $query->whereNotNull('last_contacted_at')
                    ->orWhereHas('activities');
            })
            ->withMin('activities', 'created_at')
            ->get();

        $hours = $rows->map(function (Lead $lead): ?float {
            $firstAt = $lead->getAttribute('activities_min_created_at');
            $first = $firstAt ? Carbon::parse($firstAt) : $lead->last_contacted_at;
            if ($first === null || $lead->created_at === null) {
                return null;
            }

            return max(0, $lead->created_at->diffInMinutes($first) / 60);
        })->filter(fn ($value) => $value !== null);

        return $hours->isEmpty() ? 0.0 : round((float) $hours->avg(), 1);
    }
}
