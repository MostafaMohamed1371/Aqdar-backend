<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_en')->nullable()->after('name_ar');
            $table->string('kind', 32)->default('topic')->after('slug');
            $table->string('segment', 32)->nullable()->after('kind');
            $table->string('distressed_child', 8)->nullable()->after('segment');
            $table->text('help_ar')->nullable()->after('distressed_child');
            $table->text('help_en')->nullable()->after('help_ar');
        });

        DB::table('categories')->orderBy('id')->each(function (object $row): void {
            DB::table('categories')->where('id', $row->id)->update([
                'name_ar' => $row->name_ar ?: $row->name,
                'name_en' => $row->name_en ?: $row->name,
            ]);
        });

        Schema::create('governorates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('governorate_id')->constrained('governorates')->cascadeOnDelete();
            $table->string('slug', 64);
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->unique(['governorate_id', 'slug']);
        });

        Schema::create('developers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('is_other')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')->nullable()->constrained('developers')->nullOnDelete();
            $table->string('slug', 64);
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('is_other')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->unique(['developer_id', 'slug']);
        });

        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->index();
            $table->string('key', 64)->nullable()->index();
            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->text('body_ar')->nullable();
            $table->text('body_en')->nullable();
            $table->string('image_path')->nullable();
            $table->string('link_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_blocks');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('unit_types');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('developers');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('governorates');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'name_ar',
                'name_en',
                'kind',
                'segment',
                'distressed_child',
                'help_ar',
                'help_en',
            ]);
        });
    }
};
