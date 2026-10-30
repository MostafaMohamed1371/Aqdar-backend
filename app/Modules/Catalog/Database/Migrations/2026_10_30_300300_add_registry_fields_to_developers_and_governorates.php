<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->string('commercial_register', 32)->nullable()->after('name_en');
            $table->string('logo_path')->nullable()->after('commercial_register');
            $table->date('registered_on')->nullable()->after('logo_path');
            $table->boolean('is_verified')->default(true)->index()->after('is_other');
        });

        Schema::table('governorates', function (Blueprint $table) {
            $table->string('code', 8)->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('governorates', function (Blueprint $table) {
            $table->dropColumn('code');
        });

        Schema::table('developers', function (Blueprint $table) {
            $table->dropColumn(['commercial_register', 'logo_path', 'registered_on', 'is_verified']);
        });
    }
};
