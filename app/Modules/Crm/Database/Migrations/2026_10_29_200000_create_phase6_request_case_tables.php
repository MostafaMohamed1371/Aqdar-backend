<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_requests', function (Blueprint $table) {
            $table->id();
            $table->string('public_code', 24)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('status', 32)->default('new')->index();
            $table->string('intent', 16);
            $table->string('segment_preference', 24)->default('any');
            $table->foreignId('unit_type_id')->nullable()->constrained('unit_types')->nullOnDelete();
            $table->foreignId('governorate_id')->nullable()->constrained('governorates')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('developer_id')->nullable()->constrained('developers')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->decimal('area_sqm_min', 10, 2)->nullable();
            $table->decimal('area_sqm_max', 10, 2)->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->string('finishing', 32)->nullable();
            $table->decimal('budget_min', 14, 2)->nullable();
            $table->decimal('budget_max', 14, 2)->nullable();
            $table->decimal('down_payment', 14, 2)->nullable();
            $table->decimal('monthly_installment', 14, 2)->nullable();
            $table->unsignedTinyInteger('duration_years')->nullable();
            $table->string('timeline', 64)->nullable();
            $table->text('priorities')->nullable();
            $table->string('contact_method', 16);
            $table->timestamp('consent_at');
            $table->json('criteria')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('buyer_request_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_request_id')->constrained('buyer_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('old_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('request_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_request_id')->constrained('buyer_requests')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->string('buyer_response', 16)->nullable();
            $table->timestamps();

            $table->unique(['buyer_request_id', 'listing_id']);
        });

        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('public_code', 24)->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('buyer_request_id')->nullable()->constrained('buyer_requests')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('internal_stage', 32)->index();
            $table->string('client_label', 32);
            $table->string('status', 16)->default('open')->index();
            $table->unsignedInteger('missing_documents_count')->default(0);
            $table->text('requested_document_note')->nullable();
            $table->timestamp('last_transition_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'status']);
        });

        Schema::create('case_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('old_stage', 32)->nullable();
            $table->string('new_stage', 32);
            $table->string('reason')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamps();
        });

        Schema::create('case_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploaded_by_staff_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('kind', 32);
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 128)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_documents');
        Schema::dropIfExists('case_history');
        Schema::dropIfExists('cases');
        Schema::dropIfExists('request_matches');
        Schema::dropIfExists('buyer_request_history');
        Schema::dropIfExists('buyer_requests');
    }
};
