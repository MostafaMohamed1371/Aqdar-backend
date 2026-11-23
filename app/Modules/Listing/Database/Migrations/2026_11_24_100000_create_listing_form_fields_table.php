<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unsignedInteger('form_version');
            $table->string('field_key', 64);
            $table->string('label_ar', 191);
            $table->string('label_en', 191)->nullable();
            $table->string('type', 16);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'form_version', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_form_fields');
    }
};
