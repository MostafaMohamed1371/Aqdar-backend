<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 8)->default('ar')->after('suspend_reason');
            $table->timestamp('consent_privacy_at')->nullable()->after('locale');
            $table->timestamp('consent_terms_at')->nullable()->after('consent_privacy_at');
            $table->string('consent_version', 32)->nullable()->after('consent_terms_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'locale',
                'consent_privacy_at',
                'consent_terms_at',
                'consent_version',
            ]);
        });
    }
};
