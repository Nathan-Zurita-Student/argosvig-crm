<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_pipeline_stages', fn (Blueprint $table) => $table->json('required_fields')->nullable());
    }

    public function down(): void
    {
        Schema::table('lead_pipeline_stages', fn (Blueprint $table) => $table->dropColumn('required_fields'));
    }
};
