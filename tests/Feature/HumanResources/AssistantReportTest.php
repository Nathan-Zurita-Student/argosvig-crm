<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\HumanResources\Models\Incident;
use Webkul\HumanResources\Models\ServiceContract;
use Webkul\HumanResources\Services\AssistantReport;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

function reportActor(array $permissions, string $visibility = 'individual'): User
{
    return User::create([
        'name' => 'Report test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('test-secret'),
        'status' => 1, 'view_permission' => $visibility,
        'role_id' => Role::create(['name' => fake()->uuid(), 'description' => 'QA', 'permission_type' => 'custom', 'permissions' => $permissions])->id,
    ]);
}

it('routes an overview PDF request to the report without calling an AI provider', function () {
    config(['services.openai.key' => 'synthetic-assistant-key', 'operations.ai_enabled' => true]);
    Http::preventStrayRequests();
    Http::fake();

    $this->actingAs(reportActor(['crm-ai', 'contacts.persons']), 'user')
        ->postJson(route('admin.ai.ask'), ['question' => 'Traga todos os dados do sistema e gere um PDF completo.'])
        ->assertOk()->assertJsonPath('links.0.url', route('admin.ai.report'))->assertJsonPath('file', null);

    Http::assertNothingSent();
    expect(AssistantReport::requested('Faça um PDF completo das ocorrências'))->toBeFalse()
        ->and(AssistantReport::requested('Exportar todos os dados do CRM em XLSX'))->toBeFalse();
});

it('exports scoped rows and management columns and rejects unpermitted modules', function () {
    $owner = reportActor(['crm-ai', 'contacts.persons']);
    $outsider = reportActor(['crm-ai', 'contacts.persons']);
    Person::create(['name' => 'Listed person QA', 'user_id' => $owner->id, 'emails' => [['label' => 'work', 'value' => 'sensitive@example.test']]]);
    Person::create(['name' => 'Private person QA', 'user_id' => $outsider->id]);

    $this->actingAs($owner, 'user');
    $sections = app(AssistantReport::class)->sections();
    expect(array_column($sections, 'key'))->toBe(['people'])
        ->and($sections[0]['total'])->toBe(1)
        ->and(json_encode($sections))->toContain('Listed person QA')
        ->not->toContain('Private person QA', 'sensitive@example.test');

    $this->get(route('admin.ai.report', ['section' => 'employees']))->assertNotFound();
    $this->get(route('admin.ai.report'))->assertOk()->assertDownload('argosvig-relatorio-geral.pdf')
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs(reportActor(['contacts.persons']), 'user')->get(route('admin.ai.report'))->assertUnauthorized();
});

it('shows the exact total and serves the remaining records in a separate PDF volume', function () {
    $owner = reportActor(['crm-ai', 'contacts.persons']);
    Person::insert(array_map(fn ($index) => ['name' => 'Report volume '.$index, 'user_id' => $owner->id], range(1, 121)));
    $this->actingAs($owner, 'user');
    $report = app(AssistantReport::class);
    $first = $report->sections()[0];
    $last = $report->sections('people', 2)[0];

    expect($first['total'])->toBe(121)
        ->and($first['rows'])->toHaveCount(120)
        ->and($first['pages'])->toBe(2)
        ->and($last['rows'])->toHaveCount(1)
        ->and($last['from'])->toBe(121);
});

it('generates a scoped incident PDF using dates, severity and the recorded site', function () {
    config(['services.openai.key' => 'synthetic-assistant-key', 'operations.ai_enabled' => true]);
    Http::preventStrayRequests();
    Http::fake();
    $this->travelTo(now()->setDate(2026, 11, 15)->setTime(15, 0));
    $columns = ['description' => 'Report QA', 'status' => 'open'];
    foreach ([
        ['current', '2026-11-15 23:59:59', 'high', 'Posto QA Norte'],
        ['boundary', '2026-10-17 00:00:00', 'critical', 'Posto QA Norte'],
        ['earlier', '2026-10-16 23:59:59', 'critical', 'Posto QA Norte'],
        ['later', '2026-11-16 00:00:00', 'high', 'Posto QA Norte'],
        ['minor', '2026-11-15 12:00:00', 'low', 'Posto QA Norte'],
        ['other_site', '2026-11-15 12:00:00', 'high', 'Posto QA Sul'],
    ] as [$title, $date, $severity, $location]) {
        Incident::create($columns + [
            'code' => 'REPORT-QA-'.$title, 'title' => 'incident-qa-'.$title,
            'occurred_at' => $date, 'severity' => $severity, 'location' => $location,
        ]);
    }

    $selection = [
        'section' => 'incidents', 'from' => '2026-10-17', 'to' => '2026-11-15',
        'severity' => 'serious', 'location' => 'Posto QA Norte', 'order' => 'location',
    ];

    $this->actingAs(reportActor(['crm-ai', 'human-resources.incidents'], 'global'), 'user')
        ->postJson(route('admin.ai.ask'), ['question' => 'Gere um PDF das ocorrências graves dos últimos 30 dias no posto "Posto QA Norte" por posto.'])
        ->assertSuccessful()->assertJsonPath('links.0.url', route('admin.ai.report', $selection));
    $this->postJson(route('admin.ai.ask'), ['question' => 'Exporte ocorrências críticas de 01/11/2026 a 15/11/2026 em PDF.'])
        ->assertSuccessful()->assertJsonPath('links.0.url', route('admin.ai.report', [
            'section' => 'incidents', 'from' => '2026-11-01', 'to' => '2026-11-15', 'severity' => 'critical',
        ]));
    Http::assertNothingSent();

    $pdf = app(AssistantReport::class)->sections('incidents', filters: $selection)[0];
    expect($pdf['total'])->toBe(2)->and(array_column($pdf['rows'], 1))->toBe(['incident-qa-current', 'incident-qa-boundary']);
    $this->get(route('admin.ai.report', $selection))->assertSuccessful()->assertDownload('argosvig-relatorio-incidents.pdf');
    $this->getJson(route('admin.ai.report', [...$selection, 'to' => '2026-10-01']))->assertUnprocessable();
    $this->getJson(route('admin.ai.report', [...$selection, 'section' => 'people']))->assertUnprocessable();
    $this->getJson(route('admin.ai.report', ['section' => 'incidents', 'from' => '2026-10-11']))->assertUnprocessable();

    $this->actingAs(reportActor(['crm-ai', 'human-resources.incidents']), 'user');
    $this->get(route('admin.ai.report', $selection))->assertNotFound();
    $this->postJson(route('admin.ai.ask'), ['question' => 'Gere um PDF das ocorrências dos últimos 30 dias.'])->assertForbidden();
});

it('calculates overview priorities only for authorized modules', function () {
    $report = app(AssistantReport::class);
    $this->actingAs(reportActor(['crm-ai', 'human-resources.incidents', 'human-resources.contracts'], 'global'), 'user');
    $before = $report->priorities();

    Incident::create([
        'code' => 'REPORT-QA-OPEN', 'title' => 'Open incident QA', 'description' => 'Test',
        'occurred_at' => now(), 'severity' => 'high', 'status' => 'investigating',
    ]);
    ServiceContract::create([
        'organization_id' => Organization::create(['name' => 'Report test organization'])->id,
        'contract_number' => 'REPORT-QA-CONTRACT', 'title' => 'Expiring contract QA',
        'starts_at' => today()->subDay(), 'ends_at' => today()->addDays(30), 'status' => 'active',
    ]);

    expect($report->priorities())->toBe([
        'open_incidents' => $before['open_incidents'] + 1,
        'expiring_contracts' => $before['expiring_contracts'] + 1,
    ]);

    $this->actingAs(reportActor(['crm-ai', 'contacts.persons']), 'user');
    expect($report->priorities())->toBe([]);
});
