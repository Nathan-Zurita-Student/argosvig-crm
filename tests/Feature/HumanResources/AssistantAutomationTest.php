<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Webkul\Automation\Models\Workflow;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Core\Models\CoreConfig;
use Webkul\Core\Services\CrmAi;
use Webkul\Email\Models\Email;
use Webkul\HumanResources\Jobs\RunAiAutomation;
use Webkul\HumanResources\Models\Employee;
use Webkul\HumanResources\Models\ServiceContract;
use Webkul\HumanResources\Services\OperationsAi;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

beforeEach(function () {
    DB::table('core_config')->where('code', 'like', 'general.crm_ai.%')->delete();
    CoreConfig::create(['code' => CrmAi::SETTINGS.'provider', 'value' => 'openai']);
    config(['services.openai.key' => 'synthetic-key', 'operations.ai_enabled' => true]);
    RateLimiter::for('crm-ai', fn () => Limit::none());
    Http::preventStrayRequests();
});

/**
 * The AI answers every chat request with this JSON.
 */
function aiReplies(array ...$results): void
{
    $sequence = Http::sequence();

    foreach ($results as $result) {
        $sequence->push(['choices' => [['message' => ['content' => json_encode($result, JSON_THROW_ON_ERROR)]]]]);
    }

    Http::fake(['https://api.openai.com/v1/chat/completions' => $sequence]);
}

function aiRequests(): array
{
    return collect(Http::recorded())->map(fn ($pair) => $pair[0])->all();
}

it('previews a spreadsheet import, flags duplicates and errors, and creates only the new records on confirmation', function () {
    $existing = 'Existente '.uniqid();
    app(OrganizationRepository::class)->create(['name' => $existing, 'entity_type' => 'organizations']);
    $new = ['Alfa '.uniqid(), 'Beta '.uniqid()];

    $csv = "Razão Social;Cidade;UF\n{$new[0]};São Paulo;SP\n{$existing};Campinas;SP\n;Santos;SP\n{$new[1]};Curitiba;PR\n{$new[1]};Curitiba;PR\n";

    aiReplies(['answer' => 'Vou cadastrar as empresas.', 'import' => ['type' => 'organization', 'mapping' => [
        'name' => 'razão social', 'city' => 'Cidade', 'state' => 'UF', 'country' => 'Coluna inexistente',
    ]]]);

    $response = $this->actingAs(getDefaultAdmin(), 'user')->post(route('admin.ai.ask'), [
        'question' => 'Cadastre essas empresas',
        'attachment' => UploadedFile::fake()->createWithContent('empresas.csv', $csv),
    ], ['Accept' => 'application/json'])->assertOk();

    $response->assertJsonPath('attachment.name', 'empresas.csv')
        ->assertJsonPath('attachment.rows', 5)
        ->assertJsonPath('import.type', 'organization')
        ->assertJsonPath('import.new', 2)
        ->assertJsonPath('import.existing', 2)
        ->assertJsonPath('import.invalid', 1)
        ->assertJsonPath('import.errors.0.row', 4)
        ->assertJsonCount(3, 'import.columns');

    // Only the headers and a sample reached the AI, never the whole file.
    $prompt = json_decode(aiRequests()[0]['messages'][1]['content'], true);
    expect($prompt['arquivo']['colunas'])->toBe(['Razão Social', 'Cidade', 'UF'])
        ->and($prompt['arquivo']['total_de_linhas'])->toBe(5)
        ->and($prompt['cadastros_em_lote'])->toHaveKey('organization');

    expect(Organization::query()->whereIn('name', $new)->count())->toBe(0);

    $this->postJson(route('admin.ai.import'), ['token' => $response->json('import.token')])->assertOk()
        ->assertJsonPath('created', 2)
        ->assertJsonPath('skipped', 3)
        ->assertJsonPath('url', route('admin.contacts.organizations.index'));

    $created = Organization::query()->where('name', $new[0])->first();
    expect($created->address)->toMatchArray(['city' => 'São Paulo', 'state' => 'SP'])
        ->and(Organization::query()->where('name', $new[1])->count())->toBe(1)
        ->and(Organization::query()->where('name', $existing)->count())->toBe(1);

    $this->postJson(route('admin.ai.import'), ['token' => $response->json('import.token')])->assertUnprocessable();
});

it('keeps the attachment for follow-up requests and refuses an import the person may not create', function () {
    $role = Role::create(['name' => fake()->uuid(), 'description' => 'QA', 'permission_type' => 'custom', 'permissions' => ['dashboard', 'crm-ai']]);
    $user = User::create(['name' => 'QA', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('test'), 'status' => 1, 'view_permission' => 'global', 'role_id' => $role->id]);

    aiReplies(
        ['answer' => 'É uma lista de empresas.', 'import' => ['type' => 'organization', 'mapping' => ['name' => 'Nome']]],
        ['answer' => 'Tem duas linhas.'],
    );

    $first = $this->actingAs($user, 'user')->post(route('admin.ai.ask'), [
        'question' => 'O que tem aqui?',
        'attachment' => UploadedFile::fake()->createWithContent('lista.csv', "Nome\nEmpresa A\nEmpresa B\n"),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('import', null);

    $this->postJson(route('admin.ai.ask'), ['question' => 'Quantas linhas?', 'file_token' => $first->json('attachment.token')])
        ->assertOk()->assertJsonPath('answer', 'Tem duas linhas.');

    $followUp = json_decode(aiRequests()[1]['messages'][1]['content'], true);
    expect($followUp['arquivo']['nome'])->toBe('lista.csv')
        ->and($followUp)->not->toHaveKey('cadastros_em_lote');
});

it('reads Word documents on the server and sends scanned files to the AI as media', function () {
    $docx = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive;
    $zip->open($docx, ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', '<w:document><w:body><w:p><w:r><w:t>Contrato de vigilância</w:t></w:r></w:p><w:p><w:r><w:t>Valor mensal R$ 12.000,00</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();

    aiReplies(['answer' => 'Contrato lido.'], ['answer' => 'Imagem lida.']);
    $this->actingAs(getDefaultAdmin(), 'user');

    $this->post(route('admin.ai.ask'), ['question' => 'Resuma', 'attachment' => new UploadedFile($docx, 'contrato.docx', null, null, true)], ['Accept' => 'application/json'])->assertOk();
    $this->post(route('admin.ai.ask'), ['question' => 'Leia', 'attachment' => UploadedFile::fake()->image('aso.png')], ['Accept' => 'application/json'])->assertOk();

    [$document, $image] = aiRequests();
    expect(json_decode($document['messages'][1]['content'], true)['arquivo']['texto'])->toBe("Contrato de vigilância\nValor mensal R$ 12.000,00")
        ->and($image['messages'][1]['content'][1]['image_url']['url'])->toStartWith('data:image/png;base64,');
});

it('finds records from a plain-language request with whitelisted filters only', function () {
    $suffix = uniqid();
    $withContract = app(OrganizationRepository::class)->create(['name' => 'Com contrato '.$suffix, 'address' => ['state' => 'SP'], 'entity_type' => 'organizations']);
    $without = app(OrganizationRepository::class)->create(['name' => 'Sem contrato '.$suffix, 'address' => ['state' => 'SP'], 'entity_type' => 'organizations']);
    app(OrganizationRepository::class)->create(['name' => 'Outro estado '.$suffix, 'address' => ['state' => 'RJ'], 'entity_type' => 'organizations']);
    ServiceContract::create(['organization_id' => $withContract->id, 'contract_number' => 'CT-'.$suffix, 'title' => 'Vigilância', 'starts_at' => now(), 'monthly_value' => 1000, 'status' => 'active']);

    aiReplies(['answer' => 'Empresas de SP sem contrato ativo.', 'search' => ['type' => 'organization', 'filters' => [
        ['field' => 'state', 'op' => 'eq', 'value' => 'SP'],
        ['field' => 'has_active_contract', 'op' => 'is_false'],
        ['field' => 'name', 'op' => 'contains', 'value' => $suffix],
        ['field' => 'id; DROP TABLE organizations', 'op' => 'eq', 'value' => 1],
    ]]]);

    $this->actingAs(getDefaultAdmin(), 'user')->postJson(route('admin.ai.ask'), ['question' => 'Empresas de SP sem contrato ativo'])->assertOk()
        ->assertJsonPath('search.type', 'organization')
        ->assertJsonPath('search.total', 1)
        ->assertJsonPath('search.items.0.title', $without->name)
        ->assertJsonPath('search.items.0.url', route('admin.contacts.organizations.edit', $without->id));
});

it('suggests the severity and an action plan for an incident being written', function () {
    aiReplies(['severity' => 'high', 'reason' => 'Acesso não autorizado ao posto.', 'plan' => ['Acionar o supervisor', 'Revisar as câmeras', '']]);

    $this->actingAs(getDefaultAdmin(), 'user')->postJson(route('admin.ai.incident_suggestion'), [
        'title' => 'Invasão no portão', 'description' => 'Uma pessoa pulou o portão lateral durante a ronda das 3h.',
    ])->assertOk()
        ->assertJson(['severity' => 'high', 'severity_label' => trans('human_resources::app.incidents.severity.high'), 'plan' => ['Acionar o supervisor', 'Revisar as câmeras']]);

    expect(aiRequests()[0]['messages'][1]['content'])->toContain('pulou o portão');
});

it('reads the type and expiration date of an employee document being uploaded', function () {
    aiReplies(['type' => 'occupational_health', 'name' => 'ASO admissional', 'expires_at' => '2027-11-10', 'note' => 'Validade calculada em 1 ano.']);

    $this->actingAs(getDefaultAdmin(), 'user')->post(route('admin.ai.read_document'), [
        'file' => UploadedFile::fake()->image('aso.jpg'),
        'types' => ['identity', 'occupational_health', 'other'],
    ], ['Accept' => 'application/json'])->assertOk()
        ->assertJson(['type' => 'occupational_health', 'name' => 'ASO admissional', 'expires_at' => '2027-11-10']);

    expect(aiRequests()[0]['messages'][1]['content'][1]['image_url']['url'])->toStartWith('data:image/jpeg;base64,');
});

it('offers AI actions in the workflows and runs them after the response', function () {
    Bus::fake([RunAiAutomation::class]);

    $actions = app('\Webkul\Automation\Helpers\Entity')->getActions();
    expect(collect($actions['leads'])->pluck('id'))->toContain('ai_lead_summary', 'ai_lead_next_step')
        ->and(collect($actions['emails'])->pluck('id'))->toContain('ai_email_to_lead');

    Workflow::create([
        'name' => 'E-mail vira oportunidade', 'entity_type' => 'emails', 'event' => 'email.received.after',
        'condition_type' => 'and', 'conditions' => [], 'actions' => [['id' => 'ai_email_to_lead', 'value' => getDefaultAdmin()->id]],
    ]);

    Email::create(['subject' => 'Orçamento', 'from' => 'cliente@example.com', 'reply' => 'Quero orçamento', 'folders' => ['inbox'], 'source' => 'email', 'user_type' => 'person', 'unique_id' => uniqid(), 'message_id' => uniqid()]);
    Email::create(['subject' => 'Enviado', 'from' => 'equipe@example.com', 'reply' => 'Olá', 'folders' => ['sent'], 'source' => 'web', 'user_type' => 'admin', 'unique_id' => uniqid(), 'message_id' => uniqid()]);

    Bus::assertDispatchedAfterResponse(RunAiAutomation::class, 1);
    Bus::assertDispatchedAfterResponse(RunAiAutomation::class, fn ($job) => $job->action === 'ai_email_to_lead' && $job->ownerId === getDefaultAdmin()->id);
});

it('turns a commercial e-mail into an opportunity with its sender as contact', function () {
    $sender = 'novo.cliente.'.uniqid().'@example.com';
    $email = Email::create(['subject' => 'Cotação de portaria', 'name' => 'Maria Cliente', 'from' => $sender, 'reply' => '<p>Preciso de portaria 24h para meu condomínio.</p>', 'folders' => ['sent'], 'source' => 'email', 'user_type' => 'person', 'unique_id' => uniqid(), 'message_id' => uniqid()]);

    aiReplies(['is_opportunity' => true, 'title' => 'Portaria 24h - Condomínio', 'description' => 'Cliente pede portaria 24h.', 'value' => 18000, 'contact_name' => 'Maria Cliente']);

    (new RunAiAutomation('ai_email_to_lead', $email->id, getDefaultAdmin()->id))->handle(app(OperationsAi::class));

    $lead = Lead::query()->where('title', 'Portaria 24h - Condomínio')->latest('id')->first();
    expect($lead)->not->toBeNull()
        ->and((float) $lead->lead_value)->toBe(18000.0)
        ->and($lead->person->emails[0]['value'])->toBe($sender)
        ->and($email->fresh()->lead_id)->toBe($lead->id);
});

it('imports employees with Brazilian dates and masks, and rejects a repeated CPF on its own row', function () {
    $suffix = strtoupper(substr(uniqid(), -5));
    $cpf = '1'.substr((string) random_int(1000000000, 9999999999), 0, 10);
    $masked = vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($cpf));

    aiReplies(['answer' => 'Colaboradores.', 'import' => ['type' => 'employee', 'mapping' => [
        'employee_number' => 'Matrícula', 'full_name' => 'Nome', 'cpf' => 'CPF', 'hire_date' => 'Admissão',
    ]]]);

    $csv = "Matrícula;Nome;CPF;Admissão\nm{$suffix}1;Ana QA;{$cpf};05/03/2024\nm{$suffix}2;Bia QA;{$masked};06/03/2024\n";

    $response = $this->actingAs(getDefaultAdmin(), 'user')->post(route('admin.ai.ask'), [
        'question' => 'Cadastre os colaboradores',
        'attachment' => UploadedFile::fake()->createWithContent('equipe.csv', $csv),
    ], ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('import.new', 1)
        ->assertJsonPath('import.invalid', 1)
        ->assertJsonPath('import.errors.0.row', 3);

    $this->postJson(route('admin.ai.import'), ['token' => $response->json('import.token')])->assertOk()->assertJsonPath('created', 1);

    $employee = Employee::query()->where('employee_number', 'M'.$suffix.'1')->first();
    expect($employee->cpf)->toBe($masked)
        ->and($employee->hire_date->toDateString())->toBe('2024-03-05');
});
