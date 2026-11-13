<?php

use Database\Seeders\DemoCrmSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\HumanResources\Models\Department;
use Webkul\HumanResources\Models\Employee;
use Webkul\HumanResources\Models\EmployeeDocument;
use Webkul\HumanResources\Models\Incident;
use Webkul\HumanResources\Models\ServiceContract;
use Webkul\HumanResources\Models\ShiftAssignment;
use Webkul\HumanResources\Models\Vacation;
use Webkul\HumanResources\Models\WorkSite;
use Webkul\Lead\Models\Lead;
use Webkul\Product\Models\Product;
use Webkul\Quote\Models\Quote;

uses(DatabaseTransactions::class);

it('creates a complete and repeatable CRM demonstration database', function () {
    Storage::fake('local');

    $seeder = app(DemoCrmSeeder::class);

    $seeder->run();
    $seeder->run();

    expect(Organization::query()->whereIn('name', [
        'Horizonte Facilities Ltda.',
        'Edifício Comercial Torre Norte',
    ])->count())->toBe(2)
        ->and(Person::query()->where('unique_id', 'like', 'demo:%')->count())->toBe(48)
        ->and(Lead::query()->where('title', 'like', '% - DEMO %')->count())->toBe(32)
        ->and(Product::query()->where('sku', 'like', 'ARG-%')->count())->toBe(10)
        ->and(Quote::query()->where('subject', 'like', 'Proposta comercial DEMO %')->count())->toBe(12)
        ->and(Activity::query()->where('title', 'like', '% - DEMO %')->count())->toBe(24)
        ->and(Department::query()->whereIn('code', ['OPE', 'SUP', 'RH', 'COM', 'FIN', 'TI'])->count())->toBe(6)
        ->and(Employee::query()->where('employee_number', 'like', 'ARG%')->count())->toBe(30)
        ->and(ServiceContract::query()->where('contract_number', 'like', 'CTR-DEMO-%')->count())->toBe(10)
        ->and(WorkSite::query()->where('code', 'like', 'POSTO-DEMO-%')->count())->toBe(10)
        ->and(ShiftAssignment::query()->where('notes', 'Escala demonstrativa 12x36.')->count())->toBe(30)
        ->and(Incident::query()->where('code', 'like', 'OCR-DEMO-%')->count())->toBe(8)
        ->and(EmployeeDocument::query()->where('name', 'like', 'Documento demonstrativo %')->count())->toBe(12)
        ->and(Vacation::query()->where('notes', 'Período demonstrativo para planejamento operacional.')->count())->toBe(12);
});
