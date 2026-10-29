<?php

namespace App\Modules\Dashboard\Services;

use App\Core\Audit\Models\AccessLog;
use App\Core\Audit\Models\AuditLog;
use App\Modules\Setting\Services\SettingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditSearchService
{
    public function __construct(private SettingService $settings) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $kind = $this->kind($filters);
        if ($kind === 'audit' || $kind === 'access') {
            $query = $kind === 'access' ? AccessLog::query() : AuditLog::query();
            $this->applyFilters($query, $filters);

            return $query->with('actor')->latest('id')->paginate(30)->withQueryString();
        }

        return $this->paginateCombined($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $kind = $this->kind($filters);

        return response()->streamDownload(function () use ($filters, $kind): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['time', 'kind', 'actor_type', 'actor_id', 'action', 'details', 'ip']);
            foreach ($this->exportRows($filters, $kind) as $row) {
                fputcsv($out, [
                    optional($row->created_at)?->toDateTimeString(),
                    $row->log_kind ?? $kind,
                    $row->actor_type,
                    $row->actor_id,
                    $row->action,
                    self::details($row),
                    $row->ip_address,
                ]);
            }
            fclose($out);
        }, 'aqdar-audit-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function actionLabel(string $action): string
    {
        $key = 'aqdar.audit.actions.'.str_replace('.', '_', $action);
        $label = __($key);

        return $label === $key ? $action : $label;
    }

    public static function details(object $row): string
    {
        $meta = $row->meta ?? null;
        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }
        if (is_array($meta)) {
            foreach (['public_code', 'code', 'keys', 'type'] as $key) {
                if (! isset($meta[$key]) || $meta[$key] === '' || $meta[$key] === null) {
                    continue;
                }
                $value = is_array($meta[$key]) ? implode(',', $meta[$key]) : (string) $meta[$key];
                if ($value !== '' && ! str_contains(strtolower($key), 'phone') && ! str_contains(strtolower($key), 'email')) {
                    return $value;
                }
            }
        }

        $subjectType = $row->subject_type ?? null;
        $subjectId = $row->subject_id ?? null;
        if (is_string($subjectType) && $subjectType !== '') {
            return class_basename($subjectType).'#'.$subjectId;
        }

        $ip = $row->ip_address ?? null;

        return is_string($ip) && $ip !== '' ? $ip : '—';
    }

    public function pruneExpired(): int
    {
        $months = (int) $this->settings->stringValue(
            'audit_retention_months',
            (string) config('aqdar.platform.audit_retention_months', 24),
        );
        if ($months <= 0) {
            return 0;
        }
        $cutoff = now()->subMonths($months);
        $audit = AuditLog::query()->where('created_at', '<', $cutoff)->delete();
        $access = AccessLog::query()->where('created_at', '<', $cutoff)->delete();

        return $audit + $access;
    }

    /** @param array<string, mixed> $filters */
    private function kind(array $filters): string
    {
        $kind = (string) ($filters['kind'] ?? 'all');

        return in_array($kind, ['all', 'audit', 'access'], true) ? $kind : 'all';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function paginateCombined(array $filters): LengthAwarePaginator
    {
        $merged = $this->combinedRows($filters);
        $page = max(1, (int) request('page', 1));
        $perPage = 30;
        $slice = $merged->forPage($page, $perPage)->values();

        return (new Paginator($slice, $merged->count(), $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]))->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Model>
     */
    private function combinedRows(array $filters): Collection
    {
        $audit = AuditLog::query();
        $this->applyFilters($audit, $filters);
        $access = AccessLog::query();
        $this->applyFilters($access, $filters);

        return $audit->with('actor')->get()
            ->concat($access->with('actor')->get())
            ->sortByDesc(fn (Model $row): string => optional($row->created_at)?->format('Y-m-d H:i:s.u').'-'.$row->getKey())
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Traversable<int, object>
     */
    private function exportRows(array $filters, string $kind): \Traversable
    {
        if ($kind === 'all') {
            foreach ($this->combinedRows($filters) as $row) {
                yield $row;
            }

            return;
        }

        $query = $kind === 'access' ? AccessLog::query() : AuditLog::query();
        $this->applyFilters($query, $filters);
        foreach ($query->orderBy('id')->cursor() as $row) {
            $row->log_kind = $kind;
            yield $row;
        }
    }

    /** @param Builder<Model> $query */
    private function applyFilters(Builder $query, array $filters): void
    {
        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('action', 'ilike', $like)
                    ->orWhere('ip_address', 'ilike', $like)
                    ->orWhere('subject_type', 'ilike', $like);
            });
        }
        if (! empty($filters['action']) && $filters['action'] !== 'all') {
            $query->where('action', $filters['action']);
        }
        if (! empty($filters['actor_id'])) {
            $query->where('actor_id', (int) $filters['actor_id']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }
    }
}
