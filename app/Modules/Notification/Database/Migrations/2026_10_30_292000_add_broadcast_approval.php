<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_broadcasts', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('sent_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('staff_users')->nullOnDelete();
        });

        DB::table('notification_broadcasts')
            ->whereNull('approved_at')
            ->whereIn('status', ['scheduled', 'queued', 'sent'])
            ->orderBy('id')
            ->select(['id', 'sent_at', 'scheduled_at', 'created_at'])
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('notification_broadcasts')->where('id', $row->id)->update([
                        'approved_at' => $row->sent_at ?? $row->scheduled_at ?? $row->created_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('notification_broadcasts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });
    }
};
