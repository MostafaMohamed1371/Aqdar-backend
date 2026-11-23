<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrow_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method', 32);
            $table->string('reference', 128)->nullable();
            $table->string('status', 16)->default('held');
            $table->timestamp('received_at');
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('recorded_by_staff_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->foreignId('settled_by_staff_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['case_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrow_payments');
    }
};
