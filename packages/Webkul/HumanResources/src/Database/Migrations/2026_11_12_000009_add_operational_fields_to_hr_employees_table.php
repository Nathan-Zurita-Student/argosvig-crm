<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->after('birth_date');
            $table->string('pis', 20)->nullable()->unique()->after('rg');
            $table->decimal('salary', 12, 2)->nullable()->after('job_title');
            $table->date('termination_date')->nullable()->after('hire_date');
            $table->string('cnv_number', 30)->nullable()->unique()->after('termination_date');
            $table->date('cnv_issued_at')->nullable()->after('cnv_number');
            $table->date('cnv_expires_at')->nullable()->after('cnv_issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropUnique(['pis']);
            $table->dropUnique(['cnv_number']);
            $table->dropColumn(['gender', 'pis', 'salary', 'termination_date', 'cnv_number', 'cnv_issued_at', 'cnv_expires_at']);
        });
    }
};
