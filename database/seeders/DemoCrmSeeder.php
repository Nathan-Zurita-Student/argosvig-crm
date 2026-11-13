<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\HumanResources\Models\Department;
use Webkul\HumanResources\Models\Employee;
use Webkul\HumanResources\Models\EmployeeDocument;
use Webkul\HumanResources\Models\Incident;
use Webkul\HumanResources\Models\ServiceContract;
use Webkul\HumanResources\Models\ShiftAssignment;
use Webkul\HumanResources\Models\WorkSite;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Product as LeadProduct;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Type;
use Webkul\Product\Models\Product;
use Webkul\Quote\Models\Quote;
use Webkul\Quote\Models\QuoteItem;
use Webkul\User\Models\User;

class DemoCrmSeeder extends Seeder
{
    /**
     * Populate a realistic, repeatable demonstration database for Argosvig.
     */
    public function run(): void
    {
        $owner = User::query()->where('status', 1)->first();
        $pipeline = Pipeline::query()->with('stages')->first();
        $sourceIds = Source::query()->pluck('id')->values();
        $typeIds = Type::query()->pluck('id')->values();

        if (! $owner || ! $pipeline || $sourceIds->isEmpty() || $typeIds->isEmpty()) {
            throw new RuntimeException('Execute as sementes padrão do CRM antes da base demonstrativa.');
        }

        $stages = $pipeline->stages->keyBy('code');

        foreach (['new', 'follow-up', 'prospect', 'negotiation', 'won', 'lost'] as $stageCode) {
            if (! $stages->has($stageCode)) {
                throw new RuntimeException("O funil padrão não possui a etapa obrigatória: {$stageCode}.");
            }
        }

        DB::transaction(function () use ($owner, $pipeline, $sourceIds, $typeIds, $stages): void {
            $organizations = $this->createOrganizations($owner->id);
            $people = $this->createPeople($organizations, $owner->id);
            $products = $this->createProducts();
            $leads = $this->createLeads($people, $products, $owner->id, $pipeline->id, $sourceIds, $typeIds, $stages);

            $this->createQuotes($leads, $products, $owner->id);
            $this->createActivities($leads, $people, $owner->id);
            $this->createOperationalExamples($organizations, $owner->id);
        });

        $this->command?->info('Base demonstrativa criada: 16 empresas, 48 pessoas, 32 oportunidades, 10 produtos, 12 cotações, 24 atividades e operação de RH completa.');
    }

    /**
     * @return array<int, Organization>
     */
    private function createOrganizations(int $ownerId): array
    {
        $companies = [
            ['Horizonte Facilities Ltda.', 'Avenida Paulista, 1000', 'São Paulo', 'SP', '01310-100'],
            ['Atlas Condomínios S.A.', 'Rua das Palmeiras, 245', 'Campinas', 'SP', '13010-120'],
            ['Nova Aurora Logística Ltda.', 'Rodovia Anhanguera, km 92', 'Campinas', 'SP', '13098-500'],
            ['Vértice Tecnologia Corporativa', 'Alameda Santos, 780', 'São Paulo', 'SP', '01418-100'],
            ['Parque Sul Shopping Center', 'Avenida das Nações, 4500', 'São Paulo', 'SP', '04578-000'],
            ['Residencial Bosque Imperial', 'Rua dos Ipês, 88', 'Guarulhos', 'SP', '07090-020'],
            ['Indústria Metalforte Brasil', 'Avenida Industrial, 1700', 'Santo André', 'SP', '09080-500'],
            ['Hospital Santa Helena Paulista', 'Rua Vergueiro, 2100', 'São Paulo', 'SP', '04102-000'],
            ['Centro Empresarial Monte Azul', 'Avenida Faria Lima, 3100', 'São Paulo', 'SP', '04538-133'],
            ['Rede Mercantil Boa Praça', 'Rua do Comércio, 520', 'Osasco', 'SP', '06018-020'],
            ['Colégio Integração Paulista', 'Rua das Acácias, 330', 'São Bernardo do Campo', 'SP', '09720-010'],
            ['Condomínio Jardins do Vale', 'Avenida Central, 145', 'Barueri', 'SP', '06454-000'],
            ['Plena Saúde Diagnósticos', 'Rua Doutor Quirino, 910', 'Campinas', 'SP', '13015-081'],
            ['Armazéns Porto Seco Sudeste', 'Estrada Municipal, 2600', 'Jundiaí', 'SP', '13214-800'],
            ['Grupo Educacional Saber Mais', 'Avenida Ipiranga, 640', 'São Paulo', 'SP', '01040-000'],
            ['Edifício Comercial Torre Norte', 'Avenida Berrini, 1800', 'São Paulo', 'SP', '04571-000'],
        ];

        return collect($companies)->map(function (array $company) use ($ownerId): Organization {
            return Organization::query()->updateOrCreate(
                ['name' => $company[0]],
                [
                    'user_id' => $ownerId,
                    'address' => [
                        'address' => $company[1],
                        'city' => $company[2],
                        'state' => $company[3],
                        'postcode' => $company[4],
                        'country' => 'BR',
                    ],
                ]
            );
        })->all();
    }

    /**
     * @param  array<int, Organization>  $organizations
     * @return array<int, Person>
     */
    private function createPeople(array $organizations, int $ownerId): array
    {
        $names = [
            'Ana Beatriz Martins', 'Carlos Eduardo Lima', 'Mariana Oliveira Souza',
            'Rafael Gomes Ferreira', 'Juliana Costa Alves', 'Bruno Henrique Rocha',
            'Camila Rodrigues Melo', 'Felipe Augusto Nunes', 'Patrícia Santos Ribeiro',
            'Gustavo Almeida Prado', 'Renata Barbosa Castro', 'Diego Moreira Freitas',
            'Larissa Mendes Araújo', 'Marcelo Vieira Campos', 'Aline Carvalho Teixeira',
            'Rodrigo Fernandes Pires', 'Fernanda Lopes Moura', 'Thiago Correia Duarte',
            'Vanessa Martins Cunha', 'André Luiz Monteiro', 'Priscila Oliveira Ramos',
            'Leandro Santos Braga', 'Daniela Costa Peixoto', 'Eduardo Ribeiro Tavares',
            'Natália Gomes Farias', 'Ricardo Almeida Pinto', 'Bianca Souza Cardoso',
            'Henrique Lima Fonseca', 'Sabrina Rocha Azevedo', 'Alexandre Alves Coelho',
            'Isabela Mendes Rezende', 'Fábio Martins Neves', 'Débora Carvalho Paiva',
            'Vinícius Rodrigues Leal', 'Tatiane Ferreira Macedo', 'Luciano Barbosa Reis',
            'Carolina Freitas Moraes', 'Márcio Nunes Bastos', 'Letícia Castro Guimarães',
            'João Pedro Cavalcante', 'Mônica Duarte Silveira', 'Sérgio Vieira Machado',
            'Raquel Correia Novaes', 'Paulo Henrique Andrade', 'Cristiane Lopes Siqueira',
            'Roberto Carlos Figueiredo', 'Elisa Fernandes Amaral', 'Wagner Souza Brito',
        ];
        $roles = ['Gerente de Operações', 'Coordenador Administrativo', 'Analista de Contratos'];
        $people = [];

        foreach ($organizations as $organizationIndex => $organization) {
            $domain = Str::slug($organization->name).'.test';

            for ($contactIndex = 0; $contactIndex < 3; $contactIndex++) {
                $index = ($organizationIndex * 3) + $contactIndex;
                $email = sprintf('contato%02d@%s', $contactIndex + 1, $domain);
                $phone = sprintf('+55 11 9%04d-%04d', 1000 + $organizationIndex, 2000 + $index);

                $people[] = Person::query()->updateOrCreate(
                    ['unique_id' => 'demo:'.$email],
                    [
                        'name' => $names[$index],
                        'emails' => [['value' => $email, 'label' => 'work']],
                        'contact_numbers' => [['value' => $phone, 'label' => 'work']],
                        'job_title' => $roles[$contactIndex],
                        'user_id' => $ownerId,
                        'organization_id' => $organization->id,
                    ]
                );
            }
        }

        return $people;
    }

    /**
     * @return array<int, Product>
     */
    private function createProducts(): array
    {
        $catalog = [
            ['ARG-VIG-12X36', 'Vigilância patrimonial 12x36', 12800],
            ['ARG-VIG-24H', 'Posto de vigilância 24 horas', 24900],
            ['ARG-PORT-12X36', 'Portaria e controle de acesso 12x36', 9800],
            ['ARG-PORT-24H', 'Portaria e controle de acesso 24 horas', 18900],
            ['ARG-SUP-MOVEL', 'Supervisão operacional móvel', 6200],
            ['ARG-RONDA', 'Ronda preventiva programada', 4800],
            ['ARG-EVENTO', 'Segurança para eventos', 7500],
            ['ARG-CFTV', 'Monitoramento de CFTV', 8900],
            ['ARG-CONSULT', 'Consultoria de segurança patrimonial', 5500],
            ['ARG-RECEP', 'Recepção corporativa', 8200],
        ];

        return collect($catalog)->map(fn (array $item): Product => Product::query()->updateOrCreate(
            ['sku' => $item[0]],
            [
                'name' => $item[1],
                'description' => 'Serviço demonstrativo do portfólio comercial Argosvig.',
                'quantity' => 100,
                'price' => $item[2],
            ]
        ))->all();
    }

    /**
     * @param  array<int, Person>  $people
     * @param  array<int, Product>  $products
     * @return array<int, Lead>
     */
    private function createLeads($people, $products, int $ownerId, int $pipelineId, $sourceIds, $typeIds, $stages): array
    {
        $services = ['Vigilância patrimonial', 'Portaria e controle de acesso'];
        $stageCodes = ['new', 'follow-up', 'prospect', 'negotiation', 'won', 'lost'];
        $leads = [];

        for ($index = 0; $index < 32; $index++) {
            $person = $people[$index];
            $service = $services[$index % count($services)];
            $stageCode = $stageCodes[$index % count($stageCodes)];
            $organizationName = $person->organization->name;
            $title = sprintf('%s - %s - DEMO %02d', $service, $organizationName, $index + 1);
            $closedAt = in_array($stageCode, ['won', 'lost'], true) ? now()->subDays($index % 20) : null;

            $lead = Lead::query()->updateOrCreate(
                ['title' => $title],
                [
                    'description' => 'Oportunidade demonstrativa para composição de proposta, dimensionamento de equipe e visita técnica.',
                    'lead_value' => 18000 + (($index % 12) * 2750),
                    'status' => $stageCode !== 'lost',
                    'lost_reason' => $stageCode === 'lost' ? 'Condição comercial não aprovada pelo cliente.' : null,
                    'expected_close_date' => now()->addDays(10 + $index)->toDateString(),
                    'closed_at' => $closedAt,
                    'user_id' => $ownerId,
                    'person_id' => $person->id,
                    'lead_source_id' => $sourceIds[$index % $sourceIds->count()],
                    'lead_type_id' => $typeIds[$index % $typeIds->count()],
                    'lead_pipeline_id' => $pipelineId,
                    'lead_pipeline_stage_id' => $stages[$stageCode]->id,
                ]
            );

            $product = $products[$index % count($products)];
            $quantity = ($index % 3) + 1;

            LeadProduct::query()->updateOrCreate(
                ['lead_id' => $lead->id, 'product_id' => $product->id],
                ['quantity' => $quantity, 'price' => $product->price, 'amount' => $product->price * $quantity]
            );

            $leads[] = $lead;
        }

        return $leads;
    }

    /**
     * @param  array<int, Lead>  $leads
     * @param  array<int, Product>  $products
     */
    private function createQuotes(array $leads, array $products, int $ownerId): void
    {
        foreach (array_slice($leads, 0, 12) as $index => $lead) {
            $product = $products[$index % count($products)];
            $quantity = ($index % 3) + 1;
            $subTotal = (float) $product->price * $quantity;
            $discountPercent = $index % 2 === 0 ? 5 : 0;
            $discountAmount = $subTotal * ($discountPercent / 100);
            $grandTotal = $subTotal - $discountAmount;
            $address = $lead->person->organization?->address;

            $quote = Quote::query()->updateOrCreate(
                ['subject' => sprintf('Proposta comercial DEMO %02d', $index + 1)],
                [
                    'description' => 'Proposta demonstrativa para validação do fluxo comercial.',
                    'billing_address' => $address,
                    'shipping_address' => $address,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => 0,
                    'adjustment_amount' => 0,
                    'sub_total' => $subTotal,
                    'grand_total' => $grandTotal,
                    'expired_at' => now()->addDays(15 + $index),
                    'user_id' => $ownerId,
                    'person_id' => $lead->person_id,
                ]
            );

            QuoteItem::query()->updateOrCreate(
                ['quote_id' => $quote->id, 'product_id' => $product->id],
                [
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'quantity' => $quantity,
                    'price' => $product->price,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'tax_percent' => 0,
                    'tax_amount' => 0,
                    'total' => $grandTotal,
                ]
            );

            $lead->quotes()->syncWithoutDetaching([$quote->id]);
        }
    }

    /**
     * @param  array<int, Lead>  $leads
     * @param  array<int, Person>  $people
     */
    private function createActivities(array $leads, array $people, int $ownerId): void
    {
        $types = ['call', 'meeting', 'lunch'];
        $labels = ['Ligação de acompanhamento', 'Reunião de levantamento', 'Almoço comercial'];

        for ($index = 0; $index < 24; $index++) {
            $start = now()->addDays(($index % 18) - 6)->setTime(9 + ($index % 8), 0);
            $activity = Activity::query()->updateOrCreate(
                ['title' => sprintf('%s - DEMO %02d', $labels[$index % count($labels)], $index + 1)],
                [
                    'type' => $types[$index % count($types)],
                    'location' => $index % 2 === 0 ? 'Sede do cliente' : 'Reunião online',
                    'comment' => 'Atividade demonstrativa vinculada ao contato e à oportunidade.',
                    'schedule_from' => $start,
                    'schedule_to' => $start->copy()->addHour(),
                    'is_done' => $start->isPast(),
                    'user_id' => $ownerId,
                ]
            );

            $activity->leads()->syncWithoutDetaching([$leads[$index]->id]);
            $activity->persons()->syncWithoutDetaching([$people[$index]->id]);
        }
    }

    /**
     * Add realistic examples to the Argosvig-specific operational modules.
     *
     * @param  array<int, Organization>  $organizations
     */
    private function createOperationalExamples(array $organizations, int $ownerId): void
    {
        $departments = $this->createDepartments();
        $employees = $this->createEmployees($departments);
        $contracts = $this->createServiceContracts($organizations, $ownerId);
        $workSites = $this->createWorkSites($contracts);

        $this->createShiftAssignments($workSites, $employees);
        $this->createIncidents($contracts, $employees, $ownerId);
        $this->createEmployeeDocuments($employees, $ownerId);
    }

    /**
     * @return array<int, Department>
     */
    private function createDepartments(): array
    {
        $departments = [
            ['OPE', 'Operações', 'Gestão dos postos, escalas e equipes operacionais.'],
            ['SUP', 'Supervisão', 'Acompanhamento de campo e inspeções dos contratos.'],
            ['RH', 'Recursos Humanos', 'Administração de colaboradores e documentos.'],
            ['COM', 'Comercial', 'Prospecção, propostas e relacionamento com clientes.'],
            ['FIN', 'Financeiro', 'Faturamento, custos e controles financeiros.'],
            ['TI', 'Tecnologia', 'Sistemas, integrações e infraestrutura.'],
        ];

        return collect($departments)->map(fn (array $department): Department => Department::query()->updateOrCreate(
            ['code' => $department[0]],
            ['name' => $department[1], 'description' => $department[2], 'is_active' => true]
        ))->all();
    }

    /**
     * @param  array<int, Department>  $departments
     * @return array<int, Employee>
     */
    private function createEmployees(array $departments): array
    {
        $names = [
            'Adriano César Batista', 'Beatriz Helena Cardoso', 'Caio Vinícius Moraes', 'Daiane Cristina Farias',
            'Emerson Luiz de Paula', 'Flávia Regina Nascimento', 'Gilberto Alves Santana', 'Helena Maria Torres',
            'Igor Matheus Barros', 'Jéssica Fernanda Dias', 'Kleber Augusto Lopes', 'Luana Ribeiro Martins',
            'Maurício José Correia', 'Nádia Aparecida Freire', 'Otávio Henrique Soares', 'Pamela Cristina Vieira',
            'Renan Felipe Gonçalves', 'Simone de Cássia Mendes', 'Tadeu Roberto Lima', 'Úrsula Martins Rocha',
            'Valter Antônio Nogueira', 'Wesley de Souza Campos', 'Yara Cristina Oliveira', 'Zeca Carlos Monteiro',
            'Amanda Luiza Prado', 'Breno Augusto Cunha', 'Cíntia Maria Queiroz', 'Douglas Ribeiro Leite',
            'Evelyn Cristina Ramos', 'Francisco de Assis Melo',
        ];
        $jobTitles = ['Vigilante Patrimonial', 'Porteiro', 'Supervisor Operacional', 'Analista de RH', 'Executivo Comercial', 'Analista Administrativo'];
        $employees = [];

        foreach ($names as $index => $name) {
            $number = sprintf('ARG%04d', $index + 1);
            $department = $departments[$index % count($departments)];
            $status = $index === 28 ? 'on_leave' : ($index === 29 ? 'inactive' : 'active');

            $employees[] = Employee::withTrashed()->updateOrCreate(
                ['employee_number' => $number],
                [
                    'department_id' => $department->id,
                    'full_name' => $name,
                    'cpf' => sprintf('900.%03d.%03d-%02d', $index + 100, $index + 200, $index + 10),
                    'birth_date' => now()->subYears(24 + ($index % 24))->subDays($index)->toDateString(),
                    'rg' => sprintf('DEMO-%08d', $index + 1),
                    'email' => sprintf('colaborador%02d@argosvig.test', $index + 1),
                    'phone' => sprintf('+55 11 988%02d-%04d', $index, 3000 + $index),
                    'job_title' => $jobTitles[$index % count($jobTitles)],
                    'hire_date' => now()->subMonths(2 + $index)->toDateString(),
                    'status' => $status,
                    'alterdata_employee_id' => sprintf('ALT-DEMO-%04d', $index + 1),
                    'notes' => 'Cadastro demonstrativo para validação dos fluxos internos.',
                    'deleted_at' => null,
                ]
            );
        }

        return $employees;
    }

    /**
     * @param  array<int, Organization>  $organizations
     * @return array<int, ServiceContract>
     */
    private function createServiceContracts(array $organizations, int $ownerId): array
    {
        $contracts = [];

        foreach (array_slice($organizations, 0, 10) as $index => $organization) {
            $contracts[] = ServiceContract::withTrashed()->updateOrCreate(
                ['contract_number' => sprintf('CTR-DEMO-%04d', $index + 1)],
                [
                    'organization_id' => $organization->id,
                    'manager_id' => $ownerId,
                    'title' => 'Prestação de serviços de segurança - '.$organization->name,
                    'starts_at' => now()->subMonths(8 - ($index % 6))->startOfMonth()->toDateString(),
                    'ends_at' => now()->addMonths(10 + $index)->endOfMonth()->toDateString(),
                    'monthly_value' => 28000 + ($index * 4750),
                    'status' => $index === 9 ? 'draft' : 'active',
                    'description' => 'Contrato demonstrativo com equipe operacional, supervisão e controle de acesso.',
                    'deleted_at' => null,
                ]
            );
        }

        return $contracts;
    }

    /**
     * @param  array<int, ServiceContract>  $contracts
     * @return array<int, WorkSite>
     */
    private function createWorkSites(array $contracts): array
    {
        $workSites = [];

        foreach ($contracts as $index => $contract) {
            $address = $contract->organization->address ?? [];
            $workSites[] = WorkSite::withTrashed()->updateOrCreate(
                ['code' => sprintf('POSTO-DEMO-%03d', $index + 1)],
                [
                    'service_contract_id' => $contract->id,
                    'name' => 'Posto '.$contract->organization->name,
                    'address' => $address['address'] ?? null,
                    'city' => $address['city'] ?? null,
                    'state' => $address['state'] ?? null,
                    'required_staff' => 2 + ($index % 4),
                    'status' => 'active',
                    'notes' => 'Posto demonstrativo com cobertura e supervisão programadas.',
                    'deleted_at' => null,
                ]
            );
        }

        return $workSites;
    }

    /**
     * @param  array<int, WorkSite>  $workSites
     * @param  array<int, Employee>  $employees
     */
    private function createShiftAssignments(array $workSites, array $employees): void
    {
        foreach ($employees as $index => $employee) {
            $start = now()->addDays($index % 7)->setTime($index % 2 === 0 ? 7 : 19, 0);
            $end = $start->copy()->addHours(12);

            ShiftAssignment::query()->updateOrCreate(
                ['work_site_id' => $workSites[$index % count($workSites)]->id, 'employee_id' => $employee->id, 'starts_at' => $start],
                [
                    'ends_at' => $end,
                    'shift_type' => $index % 2 === 0 ? 'day' : 'night',
                    'status' => $index % 5 === 0 ? 'planned' : 'confirmed',
                    'notes' => 'Escala demonstrativa 12x36.',
                ]
            );
        }
    }

    /**
     * @param  array<int, ServiceContract>  $contracts
     * @param  array<int, Employee>  $employees
     */
    private function createIncidents(array $contracts, array $employees, int $ownerId): void
    {
        $titles = [
            'Atraso na troca de turno', 'Portão de acesso com falha', 'Visitante sem credencial', 'Ronda não registrada',
            'Alarme acionado preventivamente', 'Uniforme fora do padrão', 'Iluminação externa insuficiente', 'Tentativa de acesso não autorizado',
        ];
        $severities = ['low', 'medium', 'medium', 'high', 'low', 'low', 'medium', 'critical'];
        $statuses = ['open', 'investigating', 'resolved', 'closed', 'open', 'resolved', 'investigating', 'closed'];

        foreach ($titles as $index => $title) {
            $resolved = in_array($statuses[$index], ['resolved', 'closed'], true);

            Incident::withTrashed()->updateOrCreate(
                ['code' => sprintf('OCR-DEMO-%04d', $index + 1)],
                [
                    'title' => $title,
                    'description' => 'Ocorrência demonstrativa registrada para acompanhamento do fluxo operacional.',
                    'occurred_at' => now()->subDays($index)->setTime(8 + $index, 15),
                    'location' => 'Posto '.$contracts[$index]->organization->name,
                    'severity' => $severities[$index],
                    'status' => $statuses[$index],
                    'employee_id' => $employees[$index]->id,
                    'service_contract_id' => $contracts[$index]->id,
                    'assigned_to' => $ownerId,
                    'reported_by' => $ownerId,
                    'resolution' => $resolved ? 'Procedimento revisado e orientação registrada com a equipe.' : null,
                    'resolved_at' => $resolved ? now()->subDays(max(0, $index - 1)) : null,
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * @param  array<int, Employee>  $employees
     */
    private function createEmployeeDocuments(array $employees, int $ownerId): void
    {
        $pdf = "%PDF-1.4\n% Documento demonstrativo Argosvig\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";

        foreach (array_slice($employees, 0, 12) as $index => $employee) {
            $path = sprintf('employee-documents/%d/documento-demo-%02d.pdf', $employee->id, $index + 1);

            Storage::disk('local')->put($path, $pdf);

            EmployeeDocument::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'path' => $path],
                [
                    'type' => $index % 3 === 0 ? 'occupational_health' : ($index % 3 === 1 ? 'contract' : 'certificate'),
                    'name' => sprintf('Documento demonstrativo %02d', $index + 1),
                    'disk' => 'local',
                    'original_name' => sprintf('documento-demo-%02d.pdf', $index + 1),
                    'mime_type' => 'application/pdf',
                    'size' => strlen($pdf),
                    'expires_at' => now()->addDays(15 + ($index * 8))->toDateString(),
                    'status' => 'valid',
                    'uploaded_by' => $ownerId,
                ]
            );
        }
    }
}
