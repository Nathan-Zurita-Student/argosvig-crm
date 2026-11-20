<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\HumanResources\Models\ChecklistColumn;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_checklist_columns', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label', 60);
            $table->string('color', 20)->default('gray');
            $table->unsignedInteger('position')->default(0);
            // Cards entering a column marked here are completed and kept in the history.
            $table->boolean('completes')->default(false);
            $table->timestamps();
        });

        Schema::create('hr_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('column_id')->constrained('hr_checklist_columns')->restrictOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('work_site_id')->nullable()->constrained('hr_work_sites')->nullOnDelete();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('completed_by')->nullable();
            $table->timestamps();

            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['column_id', 'position']);
            $table->index('completed_at');
        });

        Schema::create('hr_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('hr_checklists')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('completed_by')->nullable();
            $table->timestamps();

            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
        });

        // The board starts with three columns; every one of them can be changed or removed in the settings.
        $now = now();

        DB::table('hr_checklist_columns')->insert(collect(ChecklistColumn::DEFAULTS)->values()->map(fn (array $column, int $position) => [
            ...$column,
            'label' => trans('human_resources::checklists.defaults.'.$column['key']),
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_checklist_items');
        Schema::dropIfExists('hr_checklists');
        Schema::dropIfExists('hr_checklist_columns');
    }
};
