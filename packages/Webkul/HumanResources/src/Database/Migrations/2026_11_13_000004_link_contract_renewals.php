<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_service_contracts', function (Blueprint $table) {
            $table->foreignId('renews_contract_id')->nullable()->unique()->constrained('hr_service_contracts');
        });
    }

    public function down(): void
    {
        Schema::table('hr_service_contracts', function (Blueprint $table) {
            $table->dropForeign(['renews_contract_id']);
            $table->dropUnique(['renews_contract_id']);
            $table->dropColumn('renews_contract_id');
        });
    }
};
