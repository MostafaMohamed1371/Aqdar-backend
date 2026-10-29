<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_field_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->json('payload');
            $table->timestamps();

            $table->unique(['listing_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_field_snapshots');
    }
};
