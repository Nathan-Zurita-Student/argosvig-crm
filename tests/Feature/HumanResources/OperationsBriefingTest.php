<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webkul\Contact\Models\Organization;
use Webkul\Core\Models\CoreConfig;
use Webkul\HumanResources\Models\Employee;
use Webkul\HumanResources\Models\Incident;
use Webkul\HumanResources\Models\ServiceContract;
use Webkul\HumanResources\Models\ShiftAssignment;
use Webkul\HumanResources\Models\Vacation;
use Webkul\HumanResources\Models\WorkSite;
use Webkul\HumanResources\Services\TimekeepingImporter;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

function operationsUser(array $permissions, string $scope = 'global'): User
{
    return User::create([
        'name' => 'Operations test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('test-secret'),
        'status' => 1, 'view_permission' => $scope,
        'role_id' => Role::create(['name' => fake()->uuid(), 'description' => 'Test', 'permission_type' => 'custom', 'permissions' => $permissions])->id,
    ]);
}

it('includes unresolved older incidents and links them without opening access to other roles', function () {
    $base = ['occurred_at' => now()->subWeek(), 'description' => 'Sensitive description', 'severity' => 'high', 'reported_by' => getDefaultAdmin()->id];
    $open = Incident::create(['code' => fake()->uuid(), 'title' => 'Pending gate repair', 'status' => 'open'] + $base);
    Incident::create(['code' => fake()->uuid(), 'title' => 'Historical closed case', 'status' => 'closed'] + $base);
    $url = route('admin.operations.index');

    $this->actingAs(getDefaultAdmin(), 'user')->get($url)->assertOk()
        ->assertSee($open->title)->assertSee(route('admin.human_resources.incidents.show', $open->id))
        ->assertDontSee('Historical closed case')->assertDontSee('Sensitive description');

    $this->actingAs(operationsUser(['operations-assistant']), 'user')->get($url)->assertOk()->assertDontSee($open->title);
    $this->actingAs(operationsUser(['operations-assistant', 'human-resources.incidents', 'human-resources.incidents.view'], 'individual'), 'user')
        ->get($url)->assertUnauthorized();
    $this->actingAs(operationsUser(['dashboard']), 'user')->get($url)->assertUnauthorized();
    $this->get(route('admin.dashboard.index'))->assertOk()->assertDontSee($url);
});

function enableOperationsAiForTest(): void
{
    config(['operations.ai_enabled' => true]);
    foreach (['api_key' => 'synthetic-key', 'other_model' => 'test/model'] as $name => $value) {
        CoreConfig::updateOrCreate(['code' => 'general.magic_ai.settings.'.$name], ['value' => $value]);
    }
}

it('sends only permitted operational metadata and treats the model answer as plain text', function () {
    enableOperationsAiForTest();
    $private = Incident::create([
        'code' => 'SENSITIVE-'.fake()->numerify('####'), 'title' => 'Private title', 'description' => 'Private CPF field',
        'status' => 'open', 'severity' => 'critical', 'occurred_at' => now(), 'reported_by' => getDefaultAdmin()->id,
    ]);
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => '<script>model output</script>']]]])]);
    $url = route('admin.operations.ask');
    $this->actingAs(operationsUser(['operations-assistant']), 'user')->post($url, ['question' => 'Pendências?'])->assertUnauthorized();
    Http::assertNothingSent();

    $this->actingAs(operationsUser(['operations-assistant', 'operations-assistant.ai']), 'user')
        ->post($url, ['question' => 'Pendências?'])->assertOk()->assertSee('<script>model output</script>')->assertDontSee('<script>model output</script>', false);
    Http::assertSent(fn ($request) => ! str_contains($request->body(), $private->code) && $request['provider']['data_collection'] === 'deny');

    $this->actingAs(getDefaultAdmin(), 'user')->post($url, ['question' => 'Pendências?'])->assertOk();
    Http::assertSent(fn ($request) => str_contains($request->body(), $private->code)
        && ! str_contains($request->body(), 'Private title') && ! str_contains($request->body(), 'Private CPF field'));
});

it('transcribes a valid attachment and previews a draft for an authorized creator without saving it', function () {
    enableOperationsAiForTest();
    config(['operations.transcription_model' => 'test/transcribe']);
    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/audio/transcriptions' => Http::response(['text' => 'Portão travado na troca de plantão.']),
        'https://openrouter.ai/api/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'title' => 'Portão travado', 'description' => 'Falha ao abrir o portão.', 'location' => 'Entrada', 'occurred_at' => null,
            'employee_id' => 100, 'status' => 'closed',
        ])]]]]),
    ]);
    $wav = 'RIFF'.pack('V', 38).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('Vv', 2, 0);
    $upload = UploadedFile::fake()->createWithContent('relato.wav', $wav);
    $url = route('admin.operations.incident_draft.store');
    $this->actingAs(operationsUser(['operations-assistant', 'operations-assistant.ai']), 'user')->post($url, ['audio' => $upload])->assertUnauthorized();
    Http::assertNothingSent();

    $this->actingAs(getDefaultAdmin(), 'user')->post($url, ['audio' => $upload])->assertRedirect(route('admin.human_resources.incidents.create'))
        ->assertSessionHas('incident_draft', fn ($draft) => $draft['title'] === 'Portão travado' && ! isset($draft['employee_id'], $draft['status']));
    $this->get(route('admin.human_resources.incidents.create'))->assertOk()->assertSee('Falha ao abrir o portão.');
    $this->assertDatabaseMissing('hr_incidents', ['title' => 'Portão travado']);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/audio/transcriptions') && $request['input_audio']['format'] === 'wav');
});

it('counts distinct confirmed staff and excludes overlapping shifts and vacations from coverage suggestions', function () {
    $site = WorkSite::create(['code' => fake()->uuid(), 'name' => 'Posto QA', 'status' => 'active', 'required_staff' => 2]);
    $employee = fn (string $name) => Employee::create(['employee_number' => fake()->uuid(), 'full_name' => $name, 'status' => 'active']);
    $working = $employee('Colaborador QA trabalhando');
    $available = $employee('AAA QA disponível');
    $vacation = $employee('AAA QA férias');
    $from = now()->addHours(2)->startOfMinute();
    $to = $from->copy()->addHours(4);
    $shift = ['work_site_id' => $site->id, 'employee_id' => $working->id, 'starts_at' => $from, 'ends_at' => $to, 'shift_type' => 'day', 'status' => 'confirmed'];
    ShiftAssignment::create(['starts_at' => $from->copy()->subHour()] + $shift);
    ShiftAssignment::create($shift);
    Vacation::create(['employee_id' => $vacation->id, 'starts_at' => $from->toDateString(), 'ends_at' => $to->toDateString(), 'days' => 1, 'type' => 'regular', 'status' => 'scheduled']);

    $this->actingAs(getDefaultAdmin(), 'user')->get(route('admin.operations.index', ['site' => $site->id]))->assertOk()
        ->assertSee(trans('human_resources::operations.coverage_count', ['confirmed' => 1, 'required' => 2]));
    $url = route('admin.operations.coverage', ['site' => $site->id, 'start' => $from->toDateTimeString(), 'end' => $to->toDateTimeString()]);
    $this->get($url)->assertOk()->assertSee($available->full_name)->assertDontSee($working->full_name)->assertDontSee($vacation->full_name);
    $payload = ['coverage' => 1, 'work_site_id' => $site->id, 'employee_id' => $available->id,
        'starts_at' => $from->toDateTimeString(), 'ends_at' => $to->toDateTimeString()];
    $this->get(route('admin.human_resources.shifts.create', $payload))->assertOk()->assertViewHas('suggestion', fn ($suggestion) => (int) $suggestion->employee_id === $available->id);
    $store = route('admin.human_resources.shifts.store', ['coverage' => 1]);
    $this->post($store, ['employee_id' => $vacation->id] + $payload + ['shift_type' => 'day', 'status' => 'planned'])->assertSessionHasErrors('employee_id');
    $this->post($store, $payload + ['shift_type' => 'day', 'status' => 'planned'])->assertRedirect(route('admin.human_resources.shifts.index'));
    $this->assertDatabaseHas('hr_shift_assignments', ['employee_id' => $available->id, 'work_site_id' => $site->id, 'status' => 'planned']);
    $this->actingAs(operationsUser(['operations-assistant', 'human-resources.work-sites', 'human-resources.shifts']), 'user')->get($url)->assertUnauthorized();
});

it('flags a missing clock-out for an ended shift and keeps private document metadata restricted', function () {
    $site = WorkSite::create(['code' => fake()->uuid(), 'name' => 'Posto QA ponto', 'status' => 'active']);
    $employee = Employee::create(['employee_number' => fake()->uuid(), 'full_name' => 'Ponto QA', 'status' => 'active', 'alterdata_employee_id' => fake()->uuid()]);
    $from = now()->subDay()->startOfDay()->addHours(7);
    $shift = ShiftAssignment::create(['work_site_id' => $site->id, 'employee_id' => $employee->id, 'starts_at' => $from, 'ends_at' => $from->copy()->addHours(8), 'shift_type' => 'day', 'status' => 'completed']);
    app(TimekeepingImporter::class)->import([['employee_code' => $employee->alterdata_employee_id, 'occurred_at' => $from->toDateTimeString()]], 'alterdata_csv');

    $link = route('admin.human_resources.timekeeping.index', ['employee_id' => $employee->id, 'from' => $from->toDateString(), 'to' => $from->toDateString()]);
    $url = route('admin.operations.index', ['site' => $site->id, 'from' => $from->toDateString(), 'to' => $from->toDateString()]);
    $this->actingAs(getDefaultAdmin(), 'user')->get($url)->assertOk()
        ->assertViewHas('punches', fn ($punches) => $punches->getCollection()->contains(fn ($row) => $row['shift']->id === $shift->id && $row['result'] === 'missing_end'))
        ->assertSee($link);

    $this->actingAs(operationsUser(['operations-assistant', 'human-resources.work-sites', 'human-resources.shifts']), 'user')
        ->get($url)->assertOk()->assertViewHas('punches', null)->assertDontSee($link)->assertViewHas('documents', null);
});

it('opens a contract handover from the contract and limits context to its own records', function () {
    $organization = Organization::create(['name' => 'Briefing customer']);
    $base = ['organization_id' => $organization->id, 'starts_at' => today(), 'status' => 'active'];
    $contract = ServiceContract::create(['contract_number' => fake()->uuid(), 'title' => 'Target briefing contract'] + $base);
    $other = ServiceContract::create(['contract_number' => fake()->uuid(), 'title' => 'Other briefing contract'] + $base);
    $incident = ['occurred_at' => now(), 'description' => 'Gate', 'severity' => 'high', 'status' => 'open'];
    Incident::create(['code' => fake()->uuid(), 'title' => 'Target contract incident', 'service_contract_id' => $contract->id] + $incident);
    Incident::create(['code' => fake()->uuid(), 'title' => 'Other contract incident', 'service_contract_id' => $other->id] + $incident);
    $url = route('admin.operations.index', ['contract' => $contract->id]);

    $this->actingAs(getDefaultAdmin(), 'user')->get($url)->assertOk()
        ->assertSee('Target contract incident')->assertDontSee('Other contract incident');
    $this->get(route('admin.human_resources.contracts.edit', $contract->id))->assertOk()->assertSee($url, false);
    $export = route('admin.operations.contract_report', ['contract' => $contract->id]);
    $this->get($export)->assertOk()->assertDownload('relatorio-operacional.pdf')->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs(operationsUser(['operations-assistant', 'human-resources.incidents', 'human-resources.incidents.view']), 'user')
        ->get($url)->assertNotFound();
    $this->get($export)->assertUnauthorized();
});

it('notifies permitted staff once when there are shifts remaining today', function () {
    $this->travelTo(today()->addHours(6));
    $site = WorkSite::create(['code' => fake()->uuid(), 'name' => 'Private notification site', 'status' => 'active']);
    $employee = Employee::create(['employee_number' => fake()->uuid(), 'full_name' => 'Employee QA', 'status' => 'active']);
    ShiftAssignment::create(['work_site_id' => $site->id, 'employee_id' => $employee->id, 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(9), 'shift_type' => 'day', 'status' => 'planned']);
    $permissions = ['operations-assistant', 'human-resources.work-sites', 'human-resources.shifts'];
    $allowed = operationsUser($permissions);
    $scoped = operationsUser($permissions, 'individual');
    $outsider = operationsUser(['operations-assistant']);

    $this->artisan('hr:notify-operations-handover')->assertSuccessful();
    $this->artisan('hr:notify-operations-handover')->assertSuccessful();
    $notifications = DB::table('notifications')->where('entity_type', 'hr_operations');
    expect((clone $notifications)->where('user_id', $allowed->id)->sole()->title)->not->toContain($site->name);
    expect($notifications->whereIn('user_id', [$scoped->id, $outsider->id])->exists())->toBeFalse();
});
