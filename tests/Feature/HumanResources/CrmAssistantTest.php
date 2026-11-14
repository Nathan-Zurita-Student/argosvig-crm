<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

function assistantUser(array $permissions): User
{
    $role = Role::create(['name' => fake()->uuid(), 'description' => 'QA', 'permission_type' => 'custom', 'permissions' => $permissions]);

    return User::create(['name' => 'QA', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('test'), 'status' => 1, 'view_permission' => 'individual', 'role_id' => $role->id]);
}

function assistantResponse(array $result): void
{
    config(['services.openrouter.key' => 'synthetic-assistant-key', 'operations.ai_enabled' => true]);
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => json_encode($result, JSON_THROW_ON_ERROR)]]],
    ])]);
}

it('prefills a regular contact form, checks actor and leaves the record for review', function () {
    assistantResponse([
        'answer' => 'Revise os dados.',
        'draft' => ['type' => 'person', 'fields' => ['name' => 'Contato de QA', 'email' => 'qa@example.invalid', 'user_id' => 999, 'organization_id' => 999]],
    ]);
    $permissions = ['dashboard', 'crm-ai', 'contacts.persons.create', 'contacts.persons.create.quick-create'];
    $actor = assistantUser($permissions);
    $this->actingAs($actor, 'user')->get(route('admin.dashboard.index'))->assertOk()->assertSee('v-crm-assistant', false);
    $response = $this->postJson(route('admin.ai.ask'), ['question' => 'Prepare o cadastro'])->assertOk()
        ->assertJsonPath('draft.fields.name', 'Contato de QA')->assertJsonMissingPath('draft.fields.user_id')->assertJsonMissingPath('draft.fields.organization_id');
    $token = $response->json('draft.token');
    $this->post(route('admin.ai.draft'), ['token' => $token])->assertRedirect(route('admin.contacts.persons.create'))
        ->assertSessionHasInput('emails.0.value', 'qa@example.invalid');
    $this->get(route('admin.contacts.persons.create'))->assertOk()->assertSee('Contato de QA');
    $this->assertDatabaseMissing('persons', ['name' => 'Contato de QA']);

    $this->actingAs(assistantUser($permissions), 'user')->post(route('admin.ai.draft'), ['token' => $token])->assertNotFound();
    $actor->role->update(['permissions' => ['crm-ai', 'dashboard']]);
    $this->actingAs($actor->refresh(), 'user')->post(route('admin.ai.draft'), ['token' => $token])->assertUnauthorized();
});

it('keeps scoped opportunity details out of requests for another user', function () {
    assistantResponse(['answer' => 'Resumo']);
    $actor = assistantUser(['dashboard', 'crm-ai', 'leads', 'leads.view']);
    $pipeline = securityTestPipeline();
    $lead = Lead::create(['title' => 'Lead confidencial', 'description' => 'Dados que não devem ir ao provedor', 'user_id' => $actor->id, 'lead_pipeline_id' => $pipeline->id, 'lead_pipeline_stage_id' => $pipeline->stages()->value('id')]);
    $payload = ['type' => 'lead', 'id' => $lead->id, 'question' => 'Resuma'];
    $url = route('admin.ai.ask');

    $this->actingAs(assistantUser(['leads', 'leads.view', 'crm-ai']), 'user')->postJson($url, $payload)->assertNotFound();
    $this->actingAs(assistantUser(['leads', 'leads.view']), 'user')->postJson($url, $payload)->assertUnauthorized();
    Http::assertNothingSent();
    $this->actingAs($actor, 'user')->postJson($url, $payload)->assertOk()->assertJsonPath('links.0.url', route('admin.leads.view', $lead->id));
    Http::assertSent(fn ($request) => str_contains($request->body(), 'Lead confidencial') && ! str_contains($request->body(), 'Dados que não devem ir ao provedor'));
});

it('creates a PDF and plain text from reviewed content and rejects unsupported formats', function () {
    assistantResponse(['answer' => 'Confira o documento.', 'file' => ['title' => 'Relatório QA', 'content' => 'Conteúdo sintético', 'format' => 'pdf']]);
    $this->actingAs(getDefaultAdmin(), 'user');
    $data = $this->postJson(route('admin.ai.ask'), ['question' => 'Gere um PDF'])->assertOk()->assertJsonPath('file.format', 'pdf')->json('file');
    $this->post(route('admin.ai.download'), $data)->assertOk()->assertDownload('documento-argosvig.pdf');
    $this->post(route('admin.ai.download'), ['format' => 'txt'] + $data)->assertDownload('documento-argosvig.txt');
    $this->postJson(route('admin.ai.download'), ['format' => 'exe'] + $data)->assertUnprocessable();
});

it('exports spreadsheets with generated cells stored as literal values', function () {
    assistantResponse(['answer' => 'OK']);
    $this->actingAs(getDefaultAdmin(), 'user');
    $data = ['title' => 'QA', 'content' => "Coluna;Valor\nLinha;=1+1"];
    $csv = $this->post(route('admin.ai.download'), ['format' => 'csv'] + $data)->assertDownload('documento-argosvig.csv');
    expect($csv->streamedContent())->toContain("Linha;'=1+1");
    $excel = $this->post(route('admin.ai.download'), ['format' => 'xlsx'] + $data)->assertDownload('documento-argosvig.xlsx');
    $file = tempnam(sys_get_temp_dir(), 'crm-ai-');
    try {
        file_put_contents($file, $excel->streamedContent());
        $book = (new Xlsx)->load($file);
        $cell = $book->getActiveSheet()->getCell('B2');
        expect($cell->getValue())->toBe('=1+1')->and($cell->getDataType())->toBe('s');
        $book->disconnectWorksheets();
    } finally {
        unlink($file);
    }
});
