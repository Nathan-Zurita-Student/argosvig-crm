<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webkul\HumanResources\Models\Department;
use Webkul\HumanResources\Models\Employee;

uses(DatabaseTransactions::class);

it('keeps operational navigation in priority order before administrative areas', function () {
    $rootKeys = collect(config('menu.admin'))
        ->filter(fn (array $item) => ! str_contains($item['key'], '.'))
        ->sortBy('sort')
        ->pluck('key')
        ->values();

    expect($rootKeys->search('workforce'))->toBeLessThan($rootKeys->search('work-sites'))
        ->and($rootKeys->search('workforce'))->toBeLessThan($rootKeys->search('vacations'))
        ->and($rootKeys->search('vacations'))->toBeLessThan($rootKeys->search('work-sites'))
        ->and($rootKeys->search('work-sites'))->toBeLessThan($rootKeys->search('incidents'))
        ->and($rootKeys->search('incidents'))->toBeLessThan($rootKeys->search('service-contracts'))
        ->and($rootKeys->search('service-contracts'))->toBeLessThan($rootKeys->search('departments'))
        ->and($rootKeys->search('departments'))->toBeLessThan($rootKeys->search('settings'));
});

it('shows the employee and department areas to an administrator', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->get(route('admin.dashboard.index'))
        ->assertSuccessful()
        ->assertSee(trans('human_resources::app.dashboard.title'))
        ->assertSee(trans('human_resources::app.dashboard.active_employees'));

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
            'gender' => 'female',
            'pis' => "PIS{$suffix}",
            'salary' => 3450.75,
            'cnv_number' => "CNV{$suffix}",
            'cnv_issued_at' => now()->subYear()->toDateString(),
            'cnv_expires_at' => now()->addYears(4)->toDateString(),
            'status' => 'active',
            'alterdata_employee_id' => "ALT{$suffix}",
        ])
        ->assertRedirect(route('admin.human_resources.employees.index'));

    $this->assertDatabaseHas('hr_employees', [
        'department_id' => $department->id,
        'employee_number' => "ARG{$suffix}",
        'alterdata_employee_id' => "ALT{$suffix}",
        'gender' => 'female',
        'pis' => "PIS{$suffix}",
        'salary' => '3450.75',
        'cnv_number' => "CNV{$suffix}",
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

it('stores employee documents privately and protects document ownership', function () {
    Storage::fake('local');

    $admin = getDefaultAdmin();
    $employee = Employee::query()->create([
        'employee_number' => fake()->unique()->numerify('ARG#####'),
        'full_name' => fake()->name(),
        'status' => 'active',
    ]);
    $otherEmployee = Employee::query()->create([
        'employee_number' => fake()->unique()->numerify('ARG#####'),
        'full_name' => fake()->name(),
        'status' => 'active',
    ]);

    expect($otherEmployee->id)->not->toBe($employee->id);

    test()->actingAs($admin)
        ->post(route('admin.human_resources.employee_documents.store', $employee->id), [
            'type' => 'occupational_health',
            'name' => 'ASO admissional',
            'expires_at' => now()->addYear()->toDateString(),
            'file' => UploadedFile::fake()->create('aso.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('admin.human_resources.employees.show', $employee->id));

    $document = $employee->documents()->firstOrFail();

    expect($document->employee_id)->toBe($employee->id);

    Storage::disk('local')->assertExists($document->path);

    test()->actingAs($admin)
        ->get(route('admin.human_resources.employees.show', $employee->id))
        ->assertSuccessful()
        ->assertSee($employee->full_name)
        ->assertSee('ASO admissional');

    test()->actingAs($admin)
        ->get(route('admin.human_resources.employee_documents.download', [$otherEmployee->id, $document->id]))
        ->assertNotFound();

    test()->actingAs($admin)
        ->get(route('admin.human_resources.employee_documents.download', [$employee->id, $document->id]))
        ->assertDownload('aso.pdf');

    test()->actingAs($admin)
        ->delete(route('admin.human_resources.employee_documents.delete', [$employee->id, $document->id]))
        ->assertRedirect(route('admin.human_resources.employees.show', $employee->id));

    Storage::disk('local')->assertMissing($document->path);
    $this->assertDatabaseMissing('hr_employee_documents', ['id' => $document->id]);
});
