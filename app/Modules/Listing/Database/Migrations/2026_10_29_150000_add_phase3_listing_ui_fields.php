<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('furnished', 32)->nullable()->after('finishing');
            $table->date('handover_date')->nullable()->after('furnished');
            $table->string('neighborhood', 128)->nullable()->after('handover_date');
            $table->unsignedInteger('view_count')->default(0)->after('version');
        });

        Schema::table('listing_financials', function (Blueprint $table) {
            $table->date('declared_contract_date')->nullable()->after('declared_contract_price');
            $table->string('declared_payment_system', 32)->nullable()->after('declared_remaining_installments');
            $table->string('declared_assignment_restrictions', 300)->nullable()->after('declared_cancellation_percent');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['furnished', 'handover_date', 'neighborhood', 'view_count']);
        });

        Schema::table('listing_financials', function (Blueprint $table) {
            $table->dropColumn(['declared_contract_date', 'declared_payment_system', 'declared_assignment_restrictions']);
        });
    }
};
