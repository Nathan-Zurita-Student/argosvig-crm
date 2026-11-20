<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_incident_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('hr_incidents')->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('path');
            $table->unique(['disk', 'path']);
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_incident_images');
    }
};
