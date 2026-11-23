<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_teams', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->string('sales_phone', 32)->nullable();
            $table->string('sales_hours_ar', 120)->nullable();
            $table->string('sales_hours_en', 120)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_teams');
    }
};
