<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_service_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('organization_id');
            $table->unsignedInteger('manager_id')->nullable();
            $table->string('contract_number')->unique();
            $table->string('title');
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->decimal('monthly_value', 15, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_service_contracts');
    }
};
