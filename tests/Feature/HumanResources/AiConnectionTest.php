<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webkul\Core\Models\CoreConfig;
use Webkul\Core\Services\CrmAi;
use Webkul\Lead\Services\LeadDocumentAi;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

it('moves the existing provider configuration without losing the model or exposing the key', function () {
    DB::table('core_config')->where('code', 'like', 'general.crm_ai.%')->delete();

    $legacy = [
        'general.magic_ai.settings.enable' => '1',
        'general.magic_ai.settings.api_key' => 'test-legacy-key',
        'general.magic_ai.settings.other_model' => 'test/legacy:free',
        'general.magic_ai.doc_generation.enabled' => '1',
    ];
    foreach ($legacy as $code => $value) {
        DB::table('core_config')->insert(['code' => $code, 'value' => $value]);
    }

    $migration = require base_path('packages/Webkul/Core/src/Database/Migrations/2026_11_14_000001_move_ai_settings_to_crm.php');
    $migration->up();
    $migration->up();

    $ai = app(CrmAi::class);
    $stored = DB::table('core_config')->where('code', CrmAi::keyField('openrouter'))->value('value');
    expect($ai->enabled())->toBeTrue()
        ->and($ai->model())->toBe('test/legacy:free')
        ->and(core()->getConfigData('general.crm_ai.documents.enabled'))->toBe('1')
        ->and(Crypt::decryptString(substr($stored, strlen('encrypted:'))))->toBe('test-legacy-key')
        ->and(DB::table('core_config')->whereIn('code', array_keys($legacy))->exists())->toBeFalse();
});

it('encrypts the shared key, does not render it, and keeps it on a blank save', function () {
    $secret = 'synthetic-test-key';
    $settings = ['slug' => 'general', 'slug2' => 'crm_ai'];
    $model = 'test/model:free';

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.configuration.store', $settings), ['general' => ['crm_ai' => ['settings' => [
            'openrouter_key' => $secret, 'enable' => '1', 'openrouter_model' => $model,
        ]]]])
        ->assertRedirect();
    expect(DB::table('core_config')->where('code', CrmAi::keyField('openrouter'))->value('value'))->toStartWith('encrypted:')->not->toContain($secret);
    expect(core()->getConfigData(CrmAi::keyField('openrouter')))->toBe($secret);

    $this->get(route('admin.configuration.index', $settings))->assertOk()->assertDontSee($secret, false)->assertSee(route('admin.ai.connection'));
    expect(core()->getConfigField(CrmAi::keyField('openrouter'))['validation'])->toBe('max:255');
    $this->post(route('admin.configuration.store', $settings), ['general' => ['crm_ai' => ['settings' => ['openrouter_key' => '', 'enable' => '1']]]])->assertRedirect();
    expect(core()->getConfigData(CrmAi::keyField('openrouter')))->toBe($secret);
    $this->get(route('admin.ai.connection'))->assertOk()->assertSee($model)->assertSee(CrmAi::AUDIO_MODELS['openrouter'])->assertDontSee($secret, false);
});

it('connects directly to each configured provider using its own credential', function (string $provider, string $url, string $header, string $headerValue, array $reply) {
    config(['services.openrouter.key' => 'must-not-be-used', 'operations.ai_enabled' => true]);
    CoreConfig::updateOrCreate(['code' => CrmAi::SETTINGS.'provider'], ['value' => $provider]);
    CoreConfig::updateOrCreate(['code' => CrmAi::keyField($provider)], ['value' => 'direct-test-key']);
    Http::preventStrayRequests();
    Http::fake([$url => Http::response($reply)]);

    $this->actingAs(getDefaultAdmin(), 'user')->post(route('admin.ai.connection.test'))
        ->assertRedirect(route('admin.ai.connection'))->assertSessionHas('ai_status', 'connected');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->hasHeader($header, $headerValue)
        && str_contains($request->body(), 'Respond with OK.')
        && ! str_contains($request->body(), 'must-not-be-used'));
})->with([
    'OpenAI' => ['openai', 'https://api.openai.com/v1/chat/completions', 'Authorization', 'Bearer direct-test-key', ['choices' => [['message' => ['content' => 'OK']]]]],
    'Claude' => ['anthropic', 'https://api.anthropic.com/v1/messages', 'Authorization', 'Bearer direct-test-key', ['content' => [['type' => 'text', 'text' => 'OK']]]],
    'Gemini' => ['gemini', 'https://generativelanguage.googleapis.com/v1beta/models/*:generateContent', 'x-goog-api-key', 'direct-test-key', ['candidates' => [['content' => ['parts' => [['text' => 'OK']]]]]]],
]);

it('uses the selected text provider for image imports and a separate provider for audio', function () {
    foreach (['provider' => 'anthropic', 'anthropic_key' => 'claude-test', 'audio_provider' => 'openai', 'openai_key' => 'openai-test'] as $field => $value) {
        CoreConfig::updateOrCreate(['code' => CrmAi::SETTINGS.$field], ['value' => $value]);
    }
    Http::preventStrayRequests();
    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response(['content' => [['type' => 'text', 'text' => 'Dados da imagem']]]),
        'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'Relato de teste']),
    ]);
    $image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXhQAAAAASUVORK5CYII=';
    expect(LeadDocumentAi::extractDataFromFile($image))->toBe(['text' => 'Dados da imagem'])
        ->and(app(CrmAi::class)->transcribe('synthetic wav data', 'wav'))->toBe('Relato de teste');
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer claude-test')
        && $request['messages'][0]['content'][1]['source']['media_type'] === 'image/png');
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer openai-test')
        && str_ends_with($request->url(), '/audio/transcriptions') && str_contains($request->body(), 'audio.wav'));
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
        && $request['messages'][1]['content'] === 'Respond with OK.'
        && $request['max_tokens'] === 512
        && $request['provider']['zdr'] === true);
});
