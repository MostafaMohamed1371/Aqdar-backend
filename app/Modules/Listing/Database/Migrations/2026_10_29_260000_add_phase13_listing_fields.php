<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('phase', 64)->nullable()->after('neighborhood');
            $table->string('unit_code', 64)->nullable()->after('phase');
            $table->unsignedTinyInteger('parking')->nullable()->after('unit_code');
            $table->timestamp('offer_expires_at')->nullable()->after('published_at');
            $table->foreignId('assigned_reviewer_id')->nullable()->after('user_id')->constrained('staff_users')->nullOnDelete();
            $table->string('commission_policy_code', 64)->nullable()->after('review_reference');
        });

        Schema::table('installment_items', function (Blueprint $table) {
            $table->boolean('paid')->default(false)->after('note');
            $table->foreignId('evidence_document_id')->nullable()->after('paid')->constrained('listing_documents')->nullOnDelete();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('commission_policy_code', 64)->nullable()->after('help_en');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_reviewer_id');
            $table->dropColumn(['phase', 'unit_code', 'parking', 'offer_expires_at', 'commission_policy_code']);
        });

        Schema::table('installment_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evidence_document_id');
            $table->dropColumn('paid');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('commission_policy_code');
        });
    }
};
