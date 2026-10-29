<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->unique()->constrained('cases')->cascadeOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->string('payer_party', 16);
            $table->string('basis', 16);
            $table->decimal('rate', 6, 3)->nullable();
            $table->decimal('sale_value', 14, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('tax_amount', 14, 2)->nullable();
            $table->string('invoice_reference', 64)->nullable();
            $table->string('status', 16)->index();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('commission_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('old_status', 16)->nullable();
            $table->string('new_status', 16);
            $table->text('reason');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_history');
        Schema::dropIfExists('commissions');
    }
};
