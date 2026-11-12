<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Contact\Models\Organization;

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
