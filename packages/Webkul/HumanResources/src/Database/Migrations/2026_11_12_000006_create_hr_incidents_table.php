<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description');
            $table->dateTime('occurred_at');
            $table->string('location')->nullable();
            $table->string('severity', 20)->default('medium');
            $table->string('status', 30)->default('open');
            $table->foreignId('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('service_contract_id')->nullable()->constrained('hr_service_contracts')->nullOnDelete();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->unsignedInteger('reported_by')->nullable();
            $table->text('resolution')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reported_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'severity', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_incidents');
    }
};
