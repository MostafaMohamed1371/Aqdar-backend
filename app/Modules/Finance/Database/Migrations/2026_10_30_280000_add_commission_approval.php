<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('created_by');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('staff_users')->nullOnDelete();
        });

        DB::table('commissions')
            ->whereNull('approved_at')
            ->orderBy('id')
            ->select(['id', 'created_at'])
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('commissions')->where('id', $row->id)->update(['approved_at' => $row->created_at]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });
    }
};
