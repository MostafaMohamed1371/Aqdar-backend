<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->string('contact_name', 120)->nullable()->after('registered_on');
            $table->string('contact_phone', 32)->nullable()->after('contact_name');
            $table->string('contact_email', 190)->nullable()->after('contact_phone');
            $table->string('website', 255)->nullable()->after('contact_email');
            $table->text('internal_notes')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_phone', 'contact_email', 'website', 'internal_notes']);
        });
    }
};
