<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Webkul\HumanResources\Models\Department;
use Webkul\HumanResources\Models\Employee;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

it('groups the human resources and operations areas before administration', function () {
    test()->actingAs(getDefaultAdmin(), 'user');

    $menu = menu()->getItems('admin')->keyBy(fn ($item) => $item->getKey());
    $rootKeys = $menu->keys()->values();

    expect($rootKeys->search('human-resources'))->toBeLessThan($rootKeys->search('operations'))
        ->and($rootKeys->search('operations'))->toBeLessThan($rootKeys->search('settings'))
        ->and($rootKeys)->not->toContain('workforce', 'vacations', 'work-sites', 'incidents', 'service-contracts', 'departments', 'document-center', 'timekeeping');

    expect($menu['human-resources']->getChildren()->map->getUrl()->values()->all())->toBe([
        route('admin.human_resources.employees.index'),
        route('admin.human_resources.vacations.index'),
        route('admin.human_resources.departments.index'),
        route('admin.human_resources.document_center.index'),
        route('admin.human_resources.timekeeping.index'),
    ]);

    expect($menu['operations']->getChildren()->map->getUrl()->values()->all())->toBe([
        route('admin.human_resources.work_sites.index'),
        route('admin.human_resources.shifts.index'),
        route('admin.human_resources.incidents.index'),
        route('admin.human_resources.contracts.index'),
        route('admin.human_resources.checklists.index'),
    ]);
});

it('shows a custom role only the menu entries its permissions grant', function () {
    $role = Role::create([
        'name' => 'menu-'.uniqid(),
        'description' => 'test',
        'permission_type' => 'custom',
        'permissions' => ['dashboard', 'human-resources.vacations', 'human-resources.incidents'],
    ]);

    $user = User::create([
        'name' => 'Menu User',
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $role->id,
    ]);

    test()->actingAs($user, 'user');

    $menu = menu()->getItems('admin')->keyBy(fn ($item) => $item->getKey());

    expect($menu->keys()->all())->toContain('human-resources', 'operations')
        ->and($menu['human-resources']->getChildren()->map->getUrl()->values()->all())->toBe([route('admin.human_resources.vacations.index')])
        ->and($menu['operations']->getChildren()->map->getUrl()->values()->all())->toBe([route('admin.human_resources.incidents.index')]);

    $role->update(['permissions' => ['dashboard']]);
    $user->unsetRelation('role');

    expect(menu()->getItems('admin')->map->getKey()->all())->not->toContain('human-resources', 'operations');
});

it('moves mail and both configuration areas to the header with distinct names', function () {
    $response = test()->actingAs(getDefaultAdmin(), 'user')->get(route('admin.dashboard.index'))->assertSuccessful();

    $response->assertSee(route('admin.mail.index', ['route' => 'inbox']), false)
        ->assertSee(trans('admin::app.layouts.settings-info'))
        ->assertSee(trans('admin::app.layouts.configuration-info'));

    expect(trans('admin::app.layouts.settings', [], 'pt_BR'))->not->toBe(trans('admin::app.layouts.configuration', [], 'pt_BR'));

    $sidebar = File::get(base_path('packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/desktop/index.blade.php'));

    expect($sidebar)->toContain("\$headerMenuKeys = ['mail', 'settings', 'configuration'];");
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

it('stores the CPF with the mask and rejects the same digits typed without it', function () {
    $admin = getDefaultAdmin();
    $suffix = fake()->unique()->numerify('####');
    $digits = '98'.fake()->unique()->numerify('#########');

    test()->actingAs($admin)
        ->post(route('admin.human_resources.employees.store'), [
            'employee_number' => "CPF{$suffix}",
            'full_name' => "Employee {$suffix}",
            'status' => 'active',
            'cpf' => $digits,
        ])
        ->assertRedirect();

    $masked = vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($digits));

    $this->assertDatabaseHas('hr_employees', ['employee_number' => "CPF{$suffix}", 'cpf' => $masked]);

    test()->actingAs($admin)
        ->from(route('admin.human_resources.employees.create'))
        ->post(route('admin.human_resources.employees.store'), [
            'employee_number' => "CPF{$suffix}B",
            'full_name' => "Employee {$suffix} B",
            'status' => 'active',
            'cpf' => $masked,
        ])
        ->assertSessionHasErrors('cpf');
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
