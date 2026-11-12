<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\HumanResources\Models\Department;

uses(DatabaseTransactions::class);

it('shows the employee and department areas to an administrator', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->get(route('admin.human_resources.employees.index'))
        ->assertSuccessful()
        ->assertSee(trans('human_resources::app.employees.index.title'));

    test()->actingAs($admin)
        ->get(route('admin.human_resources.departments.index'))
        ->assertSuccessful()
        ->assertSee(trans('human_resources::app.departments.index.title'));

    test()->actingAs($admin)
        ->get(route('admin.human_resources.employees.create'))
        ->assertSuccessful()
        ->assertSee(trans('human_resources::app.employees.create.title'));

    test()->actingAs($admin)
        ->get(route('admin.human_resources.departments.create'))
        ->assertSuccessful()
        ->assertSee(trans('human_resources::app.departments.create.title'));
});

it('creates a department and an employee linked to Alterdata', function () {
    $admin = getDefaultAdmin();
    $suffix = fake()->unique()->numerify('####');

    test()->actingAs($admin)
        ->post(route('admin.human_resources.departments.store'), [
            'name' => "Operations {$suffix}",
            'code' => "OPS{$suffix}",
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.human_resources.departments.index'));

    $department = Department::query()->where('code', "OPS{$suffix}")->firstOrFail();

    test()->actingAs($admin)
        ->post(route('admin.human_resources.employees.store'), [
            'department_id' => $department->id,
            'employee_number' => "ARG{$suffix}",
            'full_name' => "Employee {$suffix}",
            'email' => "employee{$suffix}@example.com",
            'status' => 'active',
            'alterdata_employee_id' => "ALT{$suffix}",
        ])
        ->assertRedirect(route('admin.human_resources.employees.index'));

    $this->assertDatabaseHas('hr_employees', [
        'department_id' => $department->id,
        'employee_number' => "ARG{$suffix}",
        'alterdata_employee_id' => "ALT{$suffix}",
    ]);
});

it('requires unique employee and Alterdata identifiers', function () {
    $admin = getDefaultAdmin();
    $suffix = fake()->unique()->numerify('####');

    $payload = [
        'employee_number' => "ARG{$suffix}",
        'full_name' => "Employee {$suffix}",
        'status' => 'active',
        'alterdata_employee_id' => "ALT{$suffix}",
    ];

    test()->actingAs($admin)
        ->post(route('admin.human_resources.employees.store'), $payload)
        ->assertRedirect();

    test()->actingAs($admin)
        ->from(route('admin.human_resources.employees.create'))
        ->post(route('admin.human_resources.employees.store'), $payload)
        ->assertRedirect(route('admin.human_resources.employees.create'))
        ->assertSessionHasErrors(['employee_number', 'alterdata_employee_id']);
});
