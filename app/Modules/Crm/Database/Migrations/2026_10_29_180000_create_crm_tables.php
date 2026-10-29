<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('public_code', 24)->unique();
            $table->string('source', 32)->index();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('status', 32)->default('new')->index();
            $table->string('priority', 16)->default('medium')->index();
            $table->string('next_action')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('sla_due_at')->nullable()->index();
            $table->timestamp('last_contacted_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->boolean('is_duplicate')->default(false);
            $table->foreignId('duplicate_of_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->decimal('seller_visible_amount', 14, 2)->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'listing_id', 'status']);
        });

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('public_code', 24)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('contact_method', 16);
            $table->string('preferred_time', 16)->nullable();
            $table->timestamp('consent_at');
            $table->string('status', 32)->default('new')->index();
            $table->timestamps();

            $table->index(['user_id', 'listing_id']);
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('staff_users')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('contacted_party', 16)->nullable();
            $table->text('body');
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('public_code', 24)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->date('requested_date');
            $table->string('requested_slot', 32);
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('requested')->index();
            $table->timestamp('consent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('leads');
    }
};
