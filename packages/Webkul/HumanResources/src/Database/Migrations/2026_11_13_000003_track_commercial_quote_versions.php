<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_quote_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('quote_id')->nullable();
            $table->foreign('quote_id')->references('id')->on('quotes')->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('status', 20);
            $table->json('snapshot');
            $table->timestamp('sent_at');
            $table->unsignedInteger('sent_by')->nullable();
            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('resolved_by')->nullable();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['quote_id', 'number']);
        });

        Schema::table('hr_service_contracts', function (Blueprint $table) {
            $table->foreignId('quote_version_id')->nullable()->unique()->constrained('hr_quote_versions');
        });
    }

    public function down(): void
    {
        Schema::table('hr_service_contracts', function (Blueprint $table) {
            $table->dropForeign(['quote_version_id']);
            $table->dropUnique(['quote_version_id']);
            $table->dropColumn('quote_version_id');
        });

        Schema::dropIfExists('hr_quote_versions');
    }
};
