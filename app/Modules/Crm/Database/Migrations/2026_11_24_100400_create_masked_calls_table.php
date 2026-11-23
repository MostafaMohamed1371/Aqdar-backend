<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masked_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('caller_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('status', 24);
            $table->string('provider_session_id', 128)->nullable();
            $table->string('proxy_number', 32)->nullable();
            $table->timestamps();

            $table->index(['case_id', 'caller_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masked_calls');
    }
};
