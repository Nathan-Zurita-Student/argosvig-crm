<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\HumanResources\Models\Incident;

uses(DatabaseTransactions::class);

it('registers and displays an operational incident', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->get(route('admin.human_resources.incidents.index'))
        ->assertSuccessful()
        ->assertSee(trans('human_resources::app.incidents.index.title'));

    test()->actingAs($admin)
        ->get(route('admin.human_resources.incidents.create'))
        ->assertSuccessful()
        ->assertSee(trans('human_resources::app.incidents.create.title'));

    test()->actingAs($admin)
        ->post(route('admin.human_resources.incidents.store'), [
            'title' => 'Acesso não autorizado',
            'description' => 'Tentativa de acesso identificada no posto principal.',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'location' => 'Portaria principal',
            'severity' => 'high',
            'status' => 'investigating',
            'assigned_to' => $admin->id,
        ])
        ->assertRedirect();

    $incident = Incident::query()->latest('id')->firstOrFail();

    expect($incident->code)->toStartWith('OCO-')
        ->and($incident->reported_by)->toBe($admin->id);

    test()->actingAs($admin)
        ->get(route('admin.human_resources.incidents.show', $incident->id))
        ->assertSuccessful()
        ->assertSee($incident->code)
        ->assertSee('Acesso não autorizado');
});

it('requires a resolution before resolving an incident', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->post(route('admin.human_resources.incidents.store'), [
            'title' => 'Ocorrência sem resolução',
            'description' => 'Descrição da ocorrência.',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'severity' => 'medium',
            'status' => 'resolved',
        ])
        ->assertSessionHasErrors('resolution');
});
