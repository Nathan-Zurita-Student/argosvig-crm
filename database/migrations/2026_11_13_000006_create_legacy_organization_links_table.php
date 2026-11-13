<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_organization_links', function (Blueprint $table) {
            $table->string('source', 40);
            $table->string('external_id', 100)->collation('utf8mb4_bin');
            $table->unsignedInteger('organization_id')->nullable();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->primary(['source', 'external_id']);
            $table->unique(['source', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_organization_links');
    }
};
