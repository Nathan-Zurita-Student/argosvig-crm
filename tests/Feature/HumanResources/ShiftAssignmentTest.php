<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\HumanResources\Models\Employee;
use Webkul\HumanResources\Models\WorkSite;

uses(DatabaseTransactions::class);

it('assigns an employee to a work site shift', function () {
    $admin = getDefaultAdmin();
    $employee = Employee::query()->create(['employee_number' => fake()->unique()->bothify('ARG-####'), 'full_name' => fake()->name(), 'status' => 'active']);
    $site = WorkSite::query()->create(['code' => fake()->unique()->bothify('PST-####'), 'name' => 'Posto Teste', 'required_staff' => 2, 'status' => 'active']);
    test()->actingAs($admin)->get(route('admin.human_resources.shifts.create'))->assertSuccessful()->assertSee(trans('human_resources::app.shifts.create.title'));
    test()->actingAs($admin)->post(route('admin.human_resources.shifts.store'), ['work_site_id' => $site->id, 'employee_id' => $employee->id, 'starts_at' => '2026-11-13 07:00:00', 'ends_at' => '2026-11-13 19:00:00', 'shift_type' => 'day', 'status' => 'confirmed'])->assertRedirect(route('admin.human_resources.shifts.index'));
    $this->assertDatabaseHas('hr_shift_assignments', ['work_site_id' => $site->id, 'employee_id' => $employee->id, 'status' => 'confirmed']);
});
