<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\HumanResources\Models\Employee;
use Webkul\HumanResources\Models\Vacation;

uses(DatabaseTransactions::class);

it('creates and lists an employee vacation period', function () {
    $admin = getDefaultAdmin();
    $employee = Employee::query()->create([
        'employee_number' => fake()->unique()->numerify('ARG#####'),
        'full_name' => fake()->name(),
        'status' => 'active',
    ]);

    test()->actingAs($admin)
        ->post(route('admin.human_resources.vacations.store'), [
            'employee_id' => $employee->id,
            'starts_at' => '2027-01-02',
            'ends_at' => '2027-01-31',
            'type' => 'regular',
            'status' => 'scheduled',
            'notes' => 'Planejamento anual.',
        ])
        ->assertRedirect(route('admin.human_resources.vacations.index'));

    $this->assertDatabaseHas('hr_vacations', [
        'employee_id' => $employee->id,
        'days' => 30,
        'type' => 'regular',
        'status' => 'scheduled',
    ]);

    test()->actingAs($admin)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('admin.human_resources.vacations.index'))
        ->assertSuccessful()
        ->assertJsonPath('records.0.employee_number', $employee->employee_number);
});

it('rejects a vacation period ending before it starts', function () {
    $admin = getDefaultAdmin();
    $employee = Employee::query()->create([
        'employee_number' => fake()->unique()->numerify('ARG#####'),
        'full_name' => fake()->name(),
        'status' => 'active',
    ]);

    test()->actingAs($admin)
        ->from(route('admin.human_resources.vacations.create'))
        ->post(route('admin.human_resources.vacations.store'), [
            'employee_id' => $employee->id,
            'starts_at' => '2027-02-10',
            'ends_at' => '2027-02-01',
            'type' => 'regular',
            'status' => 'scheduled',
        ])
        ->assertRedirect(route('admin.human_resources.vacations.create'))
        ->assertSessionHasErrors('ends_at');
});

it('rejects a vacation period overlapping another one of the same employee', function () {
    $admin = getDefaultAdmin();
    $employee = Employee::query()->create([
        'employee_number' => fake()->unique()->numerify('ARG#####'),
        'full_name' => fake()->name(),
        'status' => 'active',
    ]);
    $vacation = ['employee_id' => $employee->id, 'starts_at' => '2027-03-01', 'ends_at' => '2027-03-15', 'type' => 'split', 'status' => 'scheduled'];

    test()->actingAs($admin)->post(route('admin.human_resources.vacations.store'), $vacation)
        ->assertRedirect(route('admin.human_resources.vacations.index'));

    test()->actingAs($admin)
        ->from(route('admin.human_resources.vacations.create'))
        ->post(route('admin.human_resources.vacations.store'), ['starts_at' => '2027-03-15', 'ends_at' => '2027-03-20'] + $vacation)
        ->assertSessionHasErrors('starts_at');

    test()->actingAs($admin)
        ->post(route('admin.human_resources.vacations.store'), ['starts_at' => '2027-03-16', 'ends_at' => '2027-03-20'] + $vacation)
        ->assertRedirect(route('admin.human_resources.vacations.index'));

    $id = Vacation::query()->where('employee_id', $employee->id)->orderBy('starts_at')->value('id');

    test()->actingAs($admin)
        ->put(route('admin.human_resources.vacations.update', $id), ['ends_at' => '2027-03-14'] + $vacation)
        ->assertRedirect(route('admin.human_resources.vacations.index'));
});
