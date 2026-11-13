<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employee_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable()->change();
            $table->date('competence')->nullable()->index();
            $table->text('notes')->nullable();
        });

        Schema::create('hr_document_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->index();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('action', 24);
            $table->timestamp('created_at');
        });

        Schema::create('hr_document_organizations', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained('hr_employee_documents')->cascadeOnDelete();
            $table->unsignedInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->primary(['document_id', 'organization_id']);
        });

        Schema::create('hr_document_contracts', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained('hr_employee_documents')->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('hr_service_contracts')->cascadeOnDelete();
            $table->primary(['document_id', 'contract_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('hr_employee_documents')->whereNull('employee_id')->exists()) {
            throw new RuntimeException('Archive and remove shared documents before rolling back this migration.');
        }

        Schema::dropIfExists('hr_document_contracts');
        Schema::dropIfExists('hr_document_organizations');
        Schema::dropIfExists('hr_document_audits');

        Schema::table('hr_employee_documents', function (Blueprint $table) {
            $table->dropIndex(['competence']);
            $table->dropColumn(['competence', 'notes']);
            $table->unsignedBigInteger('employee_id')->nullable(false)->change();
        });
    }
};
