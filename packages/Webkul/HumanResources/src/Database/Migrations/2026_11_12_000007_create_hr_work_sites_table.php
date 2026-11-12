<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_work_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_contract_id')->nullable()->constrained('hr_service_contracts')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->char('state', 2)->nullable();
            $table->unsignedInteger('required_staff')->default(1);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_work_sites');
    }
};
