<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('phone', 16)->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('account_type', 32)->default('individual')->after('phone_verified_at');
            $table->boolean('is_buyer')->default(true)->after('account_type');
            $table->boolean('is_seller')->default(false)->after('is_buyer');
            $table->string('identity_status', 32)->default('unverified')->after('is_seller');
            $table->timestamp('suspended_at')->nullable()->after('identity_status');
            $table->string('suspend_reason')->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'phone_verified_at',
                'account_type',
                'is_buyer',
                'is_seller',
                'identity_status',
                'suspended_at',
                'suspend_reason',
            ]);
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
