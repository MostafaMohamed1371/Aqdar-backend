<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_requests', function (Blueprint $table) {
            $table->boolean('is_duplicate')->default(false)->after('status');
            $table->foreignId('duplicate_of_id')->nullable()->after('is_duplicate')->constrained('buyer_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('buyer_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_of_id');
            $table->dropColumn('is_duplicate');
        });
    }
};
