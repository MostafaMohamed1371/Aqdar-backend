<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->string('public_code', 16)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('draft')->index();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('segment', 32)->nullable()->index();
            $table->string('distressed_child', 8)->nullable()->index();
            $table->foreignId('governorate_id')->nullable()->constrained('governorates')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('developer_id')->nullable()->constrained('developers')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('unit_type_id')->nullable()->constrained('unit_types')->nullOnDelete();
            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->smallInteger('floor')->nullable();
            $table->string('finishing', 64)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('amenity_ids')->nullable();
            $table->json('tag_ids')->nullable();
            $table->json('media_meta')->nullable();
            $table->json('documents_meta')->nullable();
            $table->boolean('contact_flagged')->default(false)->index();
            $table->string('contact_flag_reason')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedSmallInteger('version')->default(0);
            $table->string('review_reference', 32)->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['governorate_id', 'status']);
        });

        Schema::create('listing_financials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained('listings')->cascadeOnDelete();
            $table->decimal('declared_contract_price', 14, 2)->nullable();
            $table->decimal('verified_contract_price', 14, 2)->nullable();
            $table->boolean('contract_price_verified')->default(false);
            $table->decimal('declared_total_paid', 14, 2)->nullable();
            $table->decimal('verified_total_paid', 14, 2)->nullable();
            $table->boolean('total_paid_verified')->default(false);
            $table->decimal('declared_remaining', 14, 2)->nullable();
            $table->decimal('verified_remaining', 14, 2)->nullable();
            $table->boolean('remaining_verified')->default(false);
            $table->unsignedSmallInteger('declared_overdue_count')->nullable();
            $table->unsignedSmallInteger('verified_overdue_count')->nullable();
            $table->boolean('overdue_count_verified')->default(false);
            $table->decimal('declared_overdue_amount', 14, 2)->nullable();
            $table->decimal('verified_overdue_amount', 14, 2)->nullable();
            $table->boolean('overdue_amount_verified')->default(false);
            $table->date('declared_next_due_date')->nullable();
            $table->date('verified_next_due_date')->nullable();
            $table->boolean('next_due_date_verified')->default(false);
            $table->decimal('declared_installment_amount', 14, 2)->nullable();
            $table->decimal('verified_installment_amount', 14, 2)->nullable();
            $table->boolean('installment_amount_verified')->default(false);
            $table->unsignedSmallInteger('declared_remaining_installments')->nullable();
            $table->unsignedSmallInteger('verified_remaining_installments')->nullable();
            $table->boolean('remaining_installments_verified')->default(false);
            $table->decimal('declared_seller_recovery_amount', 14, 2)->nullable();
            $table->decimal('verified_seller_recovery_amount', 14, 2)->nullable();
            $table->boolean('seller_recovery_verified')->default(false);
            $table->decimal('declared_asking_price', 14, 2)->nullable();
            $table->decimal('verified_asking_price', 14, 2)->nullable();
            $table->boolean('asking_price_verified')->default(false);
            $table->decimal('declared_cancellation_percent', 5, 2)->nullable();
            $table->decimal('verified_cancellation_percent', 5, 2)->nullable();
            $table->boolean('cancellation_percent_verified')->default(false);
            $table->timestamps();
        });

        Schema::create('installment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->date('due_date')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['listing_id', 'sequence']);
        });

        Schema::create('listing_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->nullableMorphs('actor');
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('listing_status_history');
        Schema::dropIfExists('installment_items');
        Schema::dropIfExists('listing_financials');
        Schema::dropIfExists('listings');
    }
};
