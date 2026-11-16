<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Webkul\Contact\Models\Person;
use Webkul\HumanResources\Services\AssistantReport;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

function reportActor(array $permissions): User
{
    return User::create([
        'name' => 'Report test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('test-secret'),
        'status' => 1, 'view_permission' => 'individual',
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
