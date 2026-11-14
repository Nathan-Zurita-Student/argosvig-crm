<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webkul\Core\Services\OpenRouter;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

it('encrypts the shared key, does not render it, and keeps it on a blank save', function () {
    $secret = 'synthetic-test-key';
    $settings = ['slug' => 'general', 'slug2' => 'magic_ai'];

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.configuration.store', $settings), ['general' => ['magic_ai' => ['settings' => ['api_key' => $secret, 'enable' => '1']]]])
        ->assertRedirect();
    expect(DB::table('core_config')->where('code', OpenRouter::KEY)->value('value'))->toStartWith('encrypted:')->not->toContain($secret);
    expect(core()->getConfigData(OpenRouter::KEY))->toBe($secret);

    $this->get(route('admin.configuration.index', $settings))->assertOk()->assertDontSee($secret, false)->assertSee(route('admin.ai.connection'));
    expect(core()->getConfigField(OpenRouter::KEY)['validation'])->toBe('max:255');
    $this->post(route('admin.configuration.store', $settings), ['general' => ['magic_ai' => ['settings' => ['api_key' => '', 'enable' => '1']]]])->assertRedirect();
    expect(core()->getConfigData(OpenRouter::KEY))->toBe($secret);
    $this->get(route('admin.ai.connection'))->assertOk()->assertSee(OpenRouter::MODEL)->assertSee(OpenRouter::AUDIO_MODEL)->assertDontSee($secret, false);
});

it('checks the configured provider with synthetic data only and requires configuration permission', function () {
    config(['services.openrouter.key' => 'test-environment-key', 'operations.ai_enabled' => true]);
    Http::preventStrayRequests();
    Http::fake([
        'https://openrouter.ai/api/v1/key' => Http::response(['data' => ['limit_remaining' => 12]]),
        'https://openrouter.ai/api/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'OK']]]]),
    ]);
    $role = Role::create(['name' => fake()->uuid(), 'description' => 'QA', 'permission_type' => 'custom', 'permissions' => ['dashboard']]);
    $user = User::create(['name' => 'QA', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('test'), 'status' => 1, 'view_permission' => 'global', 'role_id' => $role->id]);
    $endpoint = route('admin.ai.connection.test');
    $this->actingAs($user, 'user')->post($endpoint)->assertUnauthorized();
    Http::assertNothingSent();

    $this->actingAs(getDefaultAdmin(), 'user')->post($endpoint)
        ->assertRedirect(route('admin.ai.connection'))->assertSessionHas('ai_status', 'connected');
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-environment-key')
        && str_ends_with($request->url(), '/chat/completions')
        && $request['messages'][0]['content'] === 'Respond with OK.'
        && $request['provider']['zdr'] === true);
});
