<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FIELDS = ['asking_price', 'remaining', 'installment_amount'];

    public function up(): void
    {
        Schema::table('listing_financials', function (Blueprint $table) {
            foreach (self::FIELDS as $field) {
                $table->decimal('public_'.$field, 14, 2)->nullable()->index();
            }
        });

        DB::table('listing_financials')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $values = [];
                foreach (self::FIELDS as $field) {
                    $values['public_'.$field] = $row->{$field.'_verified'} && $row->{'verified_'.$field} !== null
                        ? $row->{'verified_'.$field}
                        : $row->{'declared_'.$field};
                }
                DB::table('listing_financials')->where('id', $row->id)->update($values);
            }
        });
    }

    public function down(): void
    {
        Schema::table('listing_financials', function (Blueprint $table) {
            foreach (self::FIELDS as $field) {
                $table->dropColumn('public_'.$field);
            }
        });
    }
};
