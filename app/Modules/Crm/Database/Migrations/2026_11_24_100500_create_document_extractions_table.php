<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_document_id')->unique()->constrained('case_documents')->cascadeOnDelete();
            $table->string('driver', 32);
            $table->string('status', 24);
            $table->json('fields')->nullable();
            $table->foreignId('reviewed_by_staff_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_extractions');
    }
};
