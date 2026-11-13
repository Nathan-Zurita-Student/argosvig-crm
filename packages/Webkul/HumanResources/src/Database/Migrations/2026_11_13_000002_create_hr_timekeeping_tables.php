<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_timekeeping_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('received');
            $table->unsignedInteger('inserted')->default(0);
            $table->timestamps();
        });

        Schema::create('hr_timekeeping_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('hr_timekeeping_batches')->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->string('source', 32);
            $table->string('external_id', 100)->nullable();
            $table->timestamps();
            $table->index('occurred_at');
            $table->unique(['employee_id', 'occurred_at']);
            $table->unique(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_timekeeping_entries');
        Schema::dropIfExists('hr_timekeeping_batches');
    }
};
