<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Webkul\Core\Models\CoreConfig;
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

function enableOperationsAiForTest(): void
{
    config(['operations.ai_enabled' => true]);
    foreach (['api_key' => 'synthetic-key', 'other_model' => 'test/model'] as $name => $value) {
        CoreConfig::updateOrCreate(['code' => 'general.magic_ai.settings.'.$name], ['value' => $value]);
    }
}

it('sends only permitted operational metadata and treats the model answer as plain text', function () {
    enableOperationsAiForTest();
    $private = Incident::create([
        'code' => 'SENSITIVE-'.fake()->numerify('####'), 'title' => 'Private title', 'description' => 'Private CPF field',
        'status' => 'open', 'severity' => 'critical', 'occurred_at' => now(), 'reported_by' => getDefaultAdmin()->id,
    ]);
    Http::preventStrayRequests();
    Http::fake(['https://openrouter.ai/api/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => '<script>model output</script>']]]])]);
    $url = route('admin.operations.ask');
    $this->actingAs(operationsUser(['operations-assistant']), 'user')->post($url, ['question' => 'Pendências?'])->assertUnauthorized();
    Http::assertNothingSent();

    $this->actingAs(operationsUser(['operations-assistant', 'operations-assistant.ai']), 'user')
        ->post($url, ['question' => 'Pendências?'])->assertOk()->assertSee('<script>model output</script>')->assertDontSee('<script>model output</script>', false);
    Http::assertSent(fn ($request) => ! str_contains($request->body(), $private->code) && $request['provider']['data_collection'] === 'deny');

    $this->actingAs(getDefaultAdmin(), 'user')->post($url, ['question' => 'Pendências?'])->assertOk();
    Http::assertSent(fn ($request) => str_contains($request->body(), $private->code)
        && ! str_contains($request->body(), 'Private title') && ! str_contains($request->body(), 'Private CPF field'));
});
