<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Product\Models\Product;
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

it('summarizes a module using only totals visible to the acting user', function () {
    assistantResponse(['answer' => 'Resumo do módulo.']);
    $actor = assistantUser(['crm-ai', 'contacts', 'contacts.persons']);
    Person::create(['name' => 'Contato particular', 'user_id' => $actor->id]);
    Person::create(['name' => 'Contato de terceiro', 'user_id' => getDefaultAdmin()->id]);
    $this->actingAs($actor, 'user');
    $this->get(route('admin.contacts.persons.index'))->assertOk()->assertSee('admin.contacts.persons.index');
    $url = route('admin.ai.ask');
    $this->postJson($url, ['type' => 'listing', 'section' => 'admin.human_resources.employees.index', 'question' => 'Resuma'])->assertUnauthorized();
    Http::assertNothingSent();
    $this->postJson($url, ['type' => 'listing', 'section' => 'admin.contacts.persons.index', 'question' => 'Resuma'])->assertOk()
        ->assertJsonPath('links.0.url', route('admin.contacts.persons.index'));
    Http::assertSent(function ($request) {
        $facts = json_decode($request['messages'][1]['content'], true)['contexto'];

        return $facts['module'] === 'person' && $facts['total'] === 1 && ! str_contains($request->body(), 'Contato particular');
    });
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

it('accepts a bounded conversation to refine a draft from the same page', function () {
    assistantResponse(['answer' => 'Revise.', 'draft' => ['type' => 'person', 'fields' => ['name' => 'Contato de QA', 'job_title' => 'Supervisão']]]);
    $this->actingAs(getDefaultAdmin(), 'user');
    $url = route('admin.ai.ask');
    $this->postJson($url, [
        'question' => 'Acrescente Supervisão como cargo.',
        'earlier_questions' => ['Cadastre Contato de QA.'],
    ])->assertOk()->assertJsonPath('draft.fields.name', 'Contato de QA')->assertJsonPath('draft.fields.job_title', 'Supervisão');
    Http::assertSent(function ($request) {
        $context = json_decode($request['messages'][1]['content'], true);

        return $context['pedidos_anteriores'] === ['Cadastre Contato de QA.']
            && $context['pergunta'] === 'Acrescente Supervisão como cargo.';
    });
    $this->postJson($url, ['question' => 'Cadastre um contato', 'earlier_questions' => ['1', '2', '3', '4']])->assertUnprocessable();
});

it('opens a native activity form and saves only after the user submits it', function () {
    assistantResponse(['answer' => 'Confira a atividade.', 'draft' => ['type' => 'activity', 'fields' => [
        'title' => 'Ligação de QA', 'type' => 'call', 'schedule_from' => '2027-01-04T09:00', 'schedule_to' => '2027-01-04T09:30',
    ]]]);
    $actor = assistantUser(['crm-ai', 'activities', 'activities.create']);
    $this->actingAs($actor, 'user');
    $token = $this->postJson(route('admin.ai.ask'), ['question' => 'Agende uma ligação.'])->assertOk()->json('draft.token');
    $this->post(route('admin.ai.draft'), ['token' => $token])->assertRedirect(route('admin.activities.create'))->assertSessionHasInput('schedule_from', '2027-01-04 09:00:00');
    $activity = session()->getOldInput();
    $this->get(route('admin.activities.create'))->assertOk()->assertSee('Ligação de QA');
    $this->assertDatabaseMissing('activities', ['title' => 'Ligação de QA']);
    $this->postJson(route('admin.activities.store'), ['schedule_to' => '2027-01-04 08:00:00'] + $activity)->assertUnprocessable();
    $this->post(route('admin.activities.store'), $activity)->assertRedirect();
    $this->assertDatabaseHas('activities', ['title' => 'Ligação de QA', 'type' => 'call', 'user_id' => $actor->id]);
});

it('uses catalog products and prices when preparing a quote', function () {
    $sku = 'ASSISTANT-'.fake()->unique()->numerify('######');
    Product::create(['name' => 'Produto catálogo QA', 'sku' => $sku, 'price' => 45.5, 'quantity' => 10]);
    assistantResponse(['answer' => 'Confira a cotação.', 'draft' => ['type' => 'quote', 'fields' => [
        'subject' => 'Cotação QA',
        'items' => [
            ['product_sku' => $sku, 'quantity' => 3, 'price' => 0.1, 'product_id' => 999],
            ['product_sku' => 'UNKNOWN-SKU-QA', 'quantity' => 1],
        ],
    ]]]);
    $this->actingAs(getDefaultAdmin(), 'user');
    $url = route('admin.ai.ask');
    $response = $this->postJson($url, ['question' => 'Prepare a cotação com 3 unidades do SKU '.$sku])->assertOk()
        ->assertJsonMissingPath('draft.fields.items.0.price')->assertJsonMissingPath('draft.fields.items.0.product_id');
    $this->post(route('admin.ai.draft'), ['token' => $response->json('draft.token')])->assertRedirect(route('admin.quotes.create'));
    $this->get(route('admin.quotes.create'))->assertOk()->assertSee('Produto cat', false)
        ->assertSee('45.5', false)->assertSee('136.5', false)->assertDontSee('UNKNOWN-SKU-QA');
    $this->assertDatabaseMissing('quotes', ['subject' => 'Cotação QA']);

    $actor = assistantUser(['crm-ai', 'quotes', 'quotes.create', 'quotes.create.quick-create']);
    $this->actingAs($actor, 'user')->postJson($url, ['question' => 'Prepare a cotação.'])->assertOk()
        ->assertJsonPath('draft.fields.subject', 'Cotação QA')->assertJsonMissingPath('draft.fields.items');
});

it('resolves only unique visible relationships in a native draft', function () {
    assistantResponse(['answer' => 'Revise.', 'draft' => ['type' => 'lead', 'fields' => ['title' => 'Proposta de teste', 'contact_name' => 'Contato escolhido']]]);
    $actor = assistantUser(['crm-ai', 'leads', 'leads.create', 'leads.create.quick-create', 'contacts', 'contacts.persons']);
    $company = Organization::create(['name' => 'Empresa QA', 'user_id' => $actor->id]);
    $person = Person::create(['name' => 'Contato escolhido', 'organization_id' => $company->id, 'user_id' => $actor->id]);
    Person::create(['name' => 'Contato escolhido', 'user_id' => getDefaultAdmin()->id]);
    $this->actingAs($actor, 'user');
    $url = route('admin.ai.ask');
    $draft = $this->postJson($url, ['question' => 'Crie a oportunidade Proposta de teste para Contato escolhido.'])->assertOk()->json('draft.token');
    $this->post(route('admin.ai.draft'), ['token' => $draft])->assertRedirect(route('admin.leads.create'))->assertSessionHasInput('person_id', $person->id);
    $this->get(route('admin.leads.create'))->assertOk()->assertSee('Contato escolhido');

    Person::create(['name' => 'Contato escolhido', 'user_id' => $actor->id]);
    $this->post(route('admin.ai.draft'), ['token' => $draft])->assertRedirect(route('admin.leads.create'));
    expect(session()->getOldInput('person_id'))->toBeNull();
});
