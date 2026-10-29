<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->string('type', 64)->index();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('disk', 64);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 128);
            $table->unsignedInteger('size_bytes');
            $table->string('review_status', 32)->default('pending')->index();
            $table->string('review_note', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'type', 'version']);
        });

        Schema::create('listing_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('original_disk', 64);
            $table->string('original_path');
            $table->string('public_path')->nullable();
            $table->string('mime', 128);
            $table->unsignedInteger('size_bytes');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('caption', 255)->nullable();
            $table->string('moderation_status', 32)->default('pending')->index();
            $table->string('hold_reason', 64)->nullable();
            $table->string('review_note', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'moderation_status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_media');
        Schema::dropIfExists('listing_documents');
    }
};
