<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\HumanResources\Models\Incident;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

function operationsUser(array $permissions, string $scope = 'global'): User
{
    return User::create([
        'name' => 'Operations test', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('test-secret'),
        'status' => 1, 'view_permission' => $scope,
        'role_id' => Role::create(['name' => fake()->uuid(), 'description' => 'Test', 'permission_type' => 'custom', 'permissions' => $permissions])->id,
    ]);
}

it('includes unresolved older incidents and links them without opening access to other roles', function () {
    $base = ['occurred_at' => now()->subWeek(), 'description' => 'Sensitive description', 'severity' => 'high', 'reported_by' => getDefaultAdmin()->id];
    $open = Incident::create(['code' => fake()->uuid(), 'title' => 'Pending gate repair', 'status' => 'open'] + $base);
    Incident::create(['code' => fake()->uuid(), 'title' => 'Historical closed case', 'status' => 'closed'] + $base);
    $url = route('admin.operations.index');

    $this->actingAs(getDefaultAdmin(), 'user')->get($url)->assertOk()
        ->assertSee($open->title)->assertSee(route('admin.human_resources.incidents.show', $open->id))
        ->assertDontSee('Historical closed case')->assertDontSee('Sensitive description');

    $this->actingAs(operationsUser(['operations-assistant']), 'user')->get($url)->assertOk()->assertDontSee($open->title);
    $this->actingAs(operationsUser(['operations-assistant', 'human-resources.incidents', 'human-resources.incidents.view'], 'individual'), 'user')
        ->get($url)->assertUnauthorized();
    $this->actingAs(operationsUser(['dashboard']), 'user')->get($url)->assertUnauthorized();
    $this->get(route('admin.dashboard.index'))->assertOk()->assertDontSee($url);
});
