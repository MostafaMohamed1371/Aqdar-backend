<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->string('status', 32)->default('open')->change();
            $table->index(['seller_id', 'status']);
        });

        Schema::table('case_history', function (Blueprint $table) {
            $table->string('old_status', 32)->nullable()->after('new_stage');
            $table->string('new_status', 32)->nullable()->after('old_status');
        });
    }

    public function down(): void
    {
        Schema::table('case_history', function (Blueprint $table) {
            $table->dropColumn(['old_status', 'new_status']);
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['seller_id', 'status']);
            $table->string('status', 16)->default('open')->change();
        });
    }
};
