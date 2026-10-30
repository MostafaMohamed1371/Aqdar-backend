<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('close_requested_at')->nullable()->after('suspended_at');
            $table->string('close_reason', 500)->nullable()->after('close_requested_at');
            $table->timestamp('closed_at')->nullable()->after('close_reason');
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('staff_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['close_requested_at', 'close_reason', 'closed_at']);
        });
    }
};
