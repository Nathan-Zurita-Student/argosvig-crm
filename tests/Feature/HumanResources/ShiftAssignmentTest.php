<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\HumanResources\Models\Employee;
use Webkul\HumanResources\Models\ShiftAssignment;
use Webkul\HumanResources\Models\Vacation;
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

it('rejects a schedule overlapping another schedule of the same employee', function () {
    $admin = getDefaultAdmin();
    $employee = Employee::query()->create(['employee_number' => fake()->unique()->bothify('ARG-####'), 'full_name' => fake()->name(), 'status' => 'active']);
    $site = WorkSite::query()->create(['code' => fake()->unique()->bothify('PST-####'), 'name' => 'Posto Teste', 'required_staff' => 1, 'status' => 'active']);
    $shift = ['work_site_id' => $site->id, 'employee_id' => $employee->id, 'starts_at' => '2026-11-20 07:00:00', 'ends_at' => '2026-11-20 19:00:00', 'shift_type' => 'day', 'status' => 'confirmed'];

    test()->actingAs($admin)->post(route('admin.human_resources.shifts.store'), $shift)->assertRedirect(route('admin.human_resources.shifts.index'));

    test()->actingAs($admin)->from(route('admin.human_resources.shifts.create'))
        ->post(route('admin.human_resources.shifts.store'), ['starts_at' => '2026-11-20 18:00:00', 'ends_at' => '2026-11-21 06:00:00', 'shift_type' => 'night'] + $shift)
        ->assertRedirect(route('admin.human_resources.shifts.create'))
        ->assertSessionHasErrors('employee_id');

    $this->assertSame(1, ShiftAssignment::query()->where('employee_id', $employee->id)->count());

    $id = ShiftAssignment::query()->where('employee_id', $employee->id)->value('id');

    test()->actingAs($admin)->put(route('admin.human_resources.shifts.update', $id), ['ends_at' => '2026-11-20 20:00:00'] + $shift)
        ->assertRedirect(route('admin.human_resources.shifts.index'));

    test()->actingAs($admin)->post(route('admin.human_resources.shifts.store'), ['starts_at' => '2026-11-20 20:00:00', 'ends_at' => '2026-11-21 06:00:00', 'shift_type' => 'night'] + $shift)
        ->assertRedirect(route('admin.human_resources.shifts.index'));
});

it('rejects a schedule during a vacation of the employee', function () {
    $admin = getDefaultAdmin();
    $employee = Employee::query()->create(['employee_number' => fake()->unique()->bothify('ARG-####'), 'full_name' => fake()->name(), 'status' => 'active']);
    $site = WorkSite::query()->create(['code' => fake()->unique()->bothify('PST-####'), 'name' => 'Posto Teste', 'required_staff' => 1, 'status' => 'active']);
    Vacation::query()->create(['employee_id' => $employee->id, 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-10', 'days' => 10, 'type' => 'regular', 'status' => 'scheduled']);

    test()->actingAs($admin)->from(route('admin.human_resources.shifts.create'))
        ->post(route('admin.human_resources.shifts.store'), ['work_site_id' => $site->id, 'employee_id' => $employee->id, 'starts_at' => '2026-12-05 07:00:00', 'ends_at' => '2026-12-05 19:00:00', 'shift_type' => 'day', 'status' => 'planned'])
        ->assertSessionHasErrors('employee_id');

    test()->actingAs($admin)
        ->post(route('admin.human_resources.shifts.store'), ['work_site_id' => $site->id, 'employee_id' => $employee->id, 'starts_at' => '2026-12-05 07:00:00', 'ends_at' => '2026-12-05 19:00:00', 'shift_type' => 'day', 'status' => 'absence'])
        ->assertRedirect(route('admin.human_resources.shifts.index'));
});
