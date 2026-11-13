<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Contact\Models\Organization;
use Webkul\HumanResources\Models\ServiceContract;

uses(DatabaseTransactions::class);

it('creates and lists a customer service contract', function () {
    $admin = getDefaultAdmin();
    $organization = Organization::query()->create(['name' => 'Cliente '.fake()->unique()->numerify('#####')]);
    $number = fake()->unique()->bothify('CTR-####');

    test()->actingAs($admin)
        ->get(route('admin.human_resources.contracts.index'))
        ->assertSuccessful()
        ->assertSee(trans('human_resources::app.contracts.index.title'));

    test()->actingAs($admin)
        ->get(route('admin.human_resources.contracts.create'))
        ->assertSuccessful()
        ->assertSee(trans('human_resources::app.contracts.create.title'));

    test()->actingAs($admin)
        ->post(route('admin.human_resources.contracts.store'), [
            'organization_id' => $organization->id,
            'manager_id' => $admin->id,
            'contract_number' => $number,
            'title' => 'Vigilância patrimonial',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'monthly_value' => 25000,
            'status' => 'active',
        ])
        ->assertRedirect(route('admin.human_resources.contracts.index'));

    $this->assertDatabaseHas('hr_service_contracts', [
        'organization_id' => $organization->id,
        'contract_number' => $number,
        'status' => 'active',
    ]);

    test()->actingAs($admin)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('admin.human_resources.contracts.index'))
        ->assertSuccessful()
        ->assertJsonPath('records.0.contract_number', $number);
});

it('validates the contract term', function () {
    $admin = getDefaultAdmin();
    $organization = Organization::query()->create(['name' => 'Cliente '.fake()->unique()->numerify('#####')]);

    test()->actingAs($admin)
        ->from(route('admin.human_resources.contracts.create'))
        ->post(route('admin.human_resources.contracts.store'), [
            'organization_id' => $organization->id,
            'contract_number' => fake()->unique()->bothify('CTR-####'),
            'title' => 'Contrato inválido',
            'starts_at' => '2026-12-31',
            'ends_at' => '2026-01-01',
            'monthly_value' => 100,
            'status' => 'active',
        ])
        ->assertRedirect(route('admin.human_resources.contracts.create'))
        ->assertSessionHasErrors('ends_at');
});

it('filters expiring contracts and records a single non-overlapping renewal draft', function () {
    $company = Organization::create(['name' => 'Renewal company']);
    $end = today()->addWeek();
    $details = [
        'organization_id' => $company->id, 'title' => 'Near-term service contract',
        'starts_at' => today()->toDateString(), 'ends_at' => $end->toDateString(), 'monthly_value' => 100, 'status' => 'active',
    ];
    $contract = ServiceContract::create(['contract_number' => 'TEST-'.fake()->uuid()] + $details);
    $far = ServiceContract::create(['contract_number' => 'TEST-'.fake()->uuid(), 'ends_at' => today()->addDays(45)->toDateString()] + $details);
    $this->actingAs(getDefaultAdmin(), 'user');

    $this->getJson(route('admin.human_resources.contracts.index', ['expiry' => 'soon']), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertJsonFragment(['contract_number' => $contract->contract_number])->assertJsonMissing(['contract_number' => $far->contract_number]);

    $create = route('admin.human_resources.contracts.create', ['renews_contract_id' => $contract->id]);
    $this->get($create)->assertOk()->assertSee($contract->contract_number);

    $payload = ['renews_contract_id' => $contract->id, 'contract_number' => 'TEST-'.fake()->uuid(), 'ends_at' => null] + $details;
    $store = route('admin.human_resources.contracts.store');
    $this->from($create)->post($store, $payload)->assertSessionHasErrors('starts_at');
    $payload['starts_at'] = $end->copy()->addDay()->toDateString();
    $this->post($store, $payload)->assertRedirect(route('admin.human_resources.contracts.index'));
    expect($contract->renewal()->sole()->status)->toBe('draft');
    expect($contract->refresh()->status)->toBe('active');
    $this->post($store, ['contract_number' => 'TEST-'.fake()->uuid()] + $payload)->assertSessionHasErrors('renews_contract_id');
});
