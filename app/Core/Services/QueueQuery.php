<?php

namespace App\Core\Services;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class QueueQuery
{
    /**
     * Apply the shared dashboard queue filters: an inclusive from/to date range and a whitelisted sort.
     *
     * @param  array<string, mixed>  $filters  Keys `from`, `to` (Y-m-d), `sort`, `dir` (asc|desc).
     * @param  array<string, string>  $sortable  Public sort key => column name.
     */
    public static function apply(Builder $query, array $filters, array $sortable, string $dateColumn = 'created_at'): Builder
    {
        $from = self::date($filters['from'] ?? null);
        if ($from !== null) {
            $query->where($dateColumn, '>=', $from->startOfDay());
        }
        $to = self::date($filters['to'] ?? null);
        if ($to !== null) {
            $query->where($dateColumn, '<=', $to->endOfDay());
        }

        $sort = (string) ($filters['sort'] ?? '');
        if (isset($sortable[$sort])) {
            $direction = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            $query->reorder($sortable[$sort], $direction)->orderBy($query->getModel()->getQualifiedKeyName(), $direction);
        }

        return $query;
    }

    /**
     * @return array{from: string, to: string, sort: string, dir: string}
     */
    public static function filtersFrom(Request $request): array
    {
        return [
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'sort' => (string) $request->query('sort', ''),
            'dir' => (string) $request->query('dir', ''),
        ];
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
