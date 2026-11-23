<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('item_key', 64);
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by_staff_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['case_id', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_checklist_items');
    }
};
