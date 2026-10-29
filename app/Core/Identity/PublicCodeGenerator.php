<?php

namespace App\Core\Identity;

use App\Core\Enums\PublicCodePrefix;
use Illuminate\Support\Facades\DB;

class PublicCodeGenerator
{
    public function next(PublicCodePrefix $prefix, ?int $year = null): string
    {
        $year = $prefix->usesYear() ? ($year ?? (int) now()->year) : 0;

        return DB::transaction(function () use ($prefix, $year): string {
            $row = DB::table('public_code_sequences')
                ->where('prefix', $prefix->value)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $existingMax = $this->existingMax($prefix, $year);
            $fromSequence = $row === null ? 0 : (int) $row->last_value;
            $next = max($fromSequence, $existingMax) + 1;

            if ($row === null) {
                DB::table('public_code_sequences')->insert([
                    'prefix' => $prefix->value,
                    'year' => $year,
                    'last_value' => $next,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('public_code_sequences')
                    ->where('id', $row->id)
                    ->update([
                        'last_value' => $next,
                        'updated_at' => now(),
                    ]);
            }

            return $this->format($prefix, $next, $year);
        });
    }

    private function existingMax(PublicCodePrefix $prefix, int $year): int
    {
        $table = match ($prefix) {
            PublicCodePrefix::Listing => 'listings',
            PublicCodePrefix::Inquiry => 'inquiries',
            PublicCodePrefix::BuyerRequest => 'buyer_requests',
            PublicCodePrefix::Lead => 'leads',
            PublicCodePrefix::Transfer => 'cases',
            PublicCodePrefix::Appointment => 'appointments',
            PublicCodePrefix::Support => 'support_threads',
        };
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return 0;
        }

        $needle = $year === 0
            ? $prefix->value.'-'
            : $prefix->value.'-'.$year.'-';

        $max = 0;
        DB::table($table)
            ->where('public_code', 'like', $needle.'%')
            ->orderByDesc('id')
            ->select('public_code')
            ->each(function ($row) use (&$max): void {
                $parts = explode('-', (string) $row->public_code);
                $max = max($max, (int) end($parts));
            });

        return $max;
    }

    private function format(PublicCodePrefix $prefix, int $value, int $year): string
    {
        if ($year === 0) {
            return sprintf('%s-%04d', $prefix->value, $value);
        }

        return sprintf('%s-%d-%05d', $prefix->value, $year, $value);
    }
}
