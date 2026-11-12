<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_site_id')->constrained('hr_work_sites')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('shift_type', 20)->default('day');
            $table->string('status', 20)->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'starts_at']);
            $table->index(['work_site_id', 'starts_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_shift_assignments');
    }
};
