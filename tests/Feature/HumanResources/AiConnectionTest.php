<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Webkul\Core\Models\CoreConfig;
use Webkul\Core\Services\AiCatalog;
use Webkul\Core\Services\CrmAi;
use Webkul\Lead\Services\LeadDocumentAi;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

beforeEach(function () {
    DB::table('core_config')->where('code', 'like', 'general.crm_ai.%')->delete();
    config(['services.openai.key' => null, 'services.anthropic.key' => null, 'services.gemini.key' => null]);

    // Several cases call the connection test within the same minute.
    RateLimiter::for('crm-ai', fn () => Limit::none());
});

it('offers only ChatGPT, Gemini and Claude, each defaulting to its recommended model', function () {
    expect(AiCatalog::providers())->toBe(['openai', 'gemini', 'anthropic'])
        ->and(array_keys(CrmAi::MODELS))->toEqualCanonicalizing(AiCatalog::providers());

    foreach (AiCatalog::providers() as $provider) {
        expect(CrmAi::MODELS[$provider])->toBe(AiCatalog::defaultModel($provider))
            ->and(isset(CrmAi::AUDIO_MODELS[$provider]))->toBe(AiCatalog::PROVIDERS[$provider]['transcribes']);

        foreach (AiCatalog::models($provider) as $model) {
            expect(trans(AiCatalog::noteKey($model['id']), [], 'pt_BR'))->not->toStartWith('human_resources::')
                ->and($model['reasoning'])->toBeBetween(1, 5)
                ->and($model['speed'])->toBeBetween(1, 5)
                ->and($model['cost'])->toBeBetween(1, 4);
        }

        // The transcription select lists the default first and describes every option.
        $audio = AiCatalog::audioModels($provider);
        expect($audio === [])->toBe(! AiCatalog::PROVIDERS[$provider]['transcribes']);

        if ($audio) {
            expect($audio[0]['id'])->toBe(CrmAi::AUDIO_MODELS[$provider]);

            foreach ($audio as $model) {
                expect(trans(AiCatalog::audioNoteKey($model['id']), [], 'pt_BR'))->not->toStartWith('human_resources::');
            }
        }
    }

    expect(app(CrmAi::class)->configured())->toBeFalse();
});

it('moves the legacy settings and no longer uses the removed OpenRouter key', function () {
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
    expect(core()->getConfigData('general.crm_ai.documents.enabled'))->toBe('1')
        ->and(DB::table('core_config')->whereIn('code', array_keys($legacy))->exists())->toBeFalse()
        ->and($ai->provider())->toBe('openai')
        ->and($ai->configured())->toBeFalse()
        ->and($ai->enabled())->toBeFalse();
});

it('saves the chosen provider, model and encrypted key, and keeps the key on a blank save', function () {
    $secret = 'synthetic-test-key';
    $settings = ['slug' => 'general', 'slug2' => 'crm_ai'];

    $this->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.configuration.store', $settings), ['general' => ['crm_ai' => ['settings' => [
            'enable' => '1', 'provider' => 'anthropic', 'anthropic_key' => $secret, 'anthropic_model' => 'claude-haiku-5-5',
        ]]]])
        ->assertRedirect();

    expect(DB::table('core_config')->where('code', CrmAi::keyField('anthropic'))->value('value'))->toStartWith('encrypted:')->not->toContain($secret);

    $ai = app(CrmAi::class);
    expect($ai->provider())->toBe('anthropic')
        ->and($ai->model())->toBe('claude-haiku-5-5')
        ->and($ai->label())->toBe('Claude · Claude Haiku 5.5')
        ->and($ai->enabled())->toBeTrue();

    $this->get(route('admin.configuration.index', $settings))->assertOk()
        ->assertSee('v-crm-ai-settings', false)
        ->assertSee('claude-sonnet-5-5')
        ->assertDontSee($secret, false)
        ->assertDontSee('openrouter');

    $this->post(route('admin.configuration.store', $settings), ['general' => ['crm_ai' => ['settings' => [
        'enable' => '1', 'provider' => 'anthropic', 'anthropic_key' => '', 'anthropic_model' => 'claude-opus-5-5',
    ]]]])->assertRedirect();

    expect(core()->getConfigData(CrmAi::keyField('anthropic')))->toBe($secret)
        ->and(app(CrmAi::class)->model())->toBe('claude-opus-5-5');

    $this->post(route('admin.configuration.store', $settings), ['general' => ['crm_ai' => ['settings' => ['provider' => 'openrouter']]]])
        ->assertSessionHasErrors(CrmAi::SETTINGS.'provider');

    $this->get(route('admin.ai.connection'))->assertRedirect(route('admin.configuration.index', $settings));
});

it('connects directly to each configured provider using its own credential', function (string $provider, string $url, string $header, string $headerValue, array $reply) {
    config(['operations.ai_enabled' => true]);
    CoreConfig::updateOrCreate(['code' => CrmAi::SETTINGS.'provider'], ['value' => $provider]);
    CoreConfig::updateOrCreate(['code' => CrmAi::keyField($provider)], ['value' => 'direct-test-key']);
    Http::preventStrayRequests();
    Http::fake([$url => Http::response($reply)]);

    $this->actingAs(getDefaultAdmin(), 'user')->post(route('admin.ai.connection.test'))
        ->assertRedirect(route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'crm_ai']))
        ->assertSessionHas('ai_status', 'connected');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->hasHeader($header, $headerValue)
        && str_contains($request->body(), 'Respond with OK.')
        && str_contains($request->url().$request->body(), CrmAi::MODELS[$provider]));
})->with([
    'ChatGPT' => ['openai', 'https://api.openai.com/v1/chat/completions', 'Authorization', 'Bearer direct-test-key', ['choices' => [['message' => ['content' => 'OK']]]]],
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
        && str_ends_with($request->url(), '/audio/transcriptions') && str_contains($request->body(), 'gpt-transcribe'));
});

it('answers the settings screen test in JSON with synthetic data only and requires configuration permission', function () {
    config(['services.openai.key' => 'test-environment-key', 'operations.ai_enabled' => true]);
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'OK']]]])]);

    $role = Role::create(['name' => fake()->uuid(), 'description' => 'QA', 'permission_type' => 'custom', 'permissions' => ['dashboard']]);
    $user = User::create(['name' => 'QA', 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('test'), 'status' => 1, 'view_permission' => 'global', 'role_id' => $role->id]);
    $endpoint = route('admin.ai.connection.test');

    $this->actingAs($user, 'user')->postJson($endpoint)->assertUnauthorized();
    Http::assertNothingSent();

    $this->actingAs(getDefaultAdmin(), 'user')->postJson($endpoint)->assertOk()
        ->assertJson(['ok' => true, 'status' => 'connected', 'model' => 'ChatGPT · GPT-6.1 Sol'])
        ->assertJsonPath('message', trans('human_resources::ai.status.connected'));

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-environment-key')
        && $request['messages'][1]['content'] === 'Respond with OK.'
        && $request['max_completion_tokens'] >= 2000
        && $request['store'] === false);
});

it('reports a rejected key and a missing key in words the administrator can act on', function () {
    config(['operations.ai_enabled' => true]);
    $this->actingAs(getDefaultAdmin(), 'user');

    $this->postJson(route('admin.ai.connection.test'))->assertOk()->assertJson(['ok' => false, 'status' => 'key_missing']);

    CoreConfig::updateOrCreate(['code' => CrmAi::keyField('gemini')], ['value' => 'rejected-key']);
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 403]], 403)]);

    $this->postJson(route('admin.ai.connection.test'))->assertOk()
        ->assertJson(['ok' => false, 'status' => 'invalid_key', 'model' => 'Gemini · Gemini 4 Hydrogen']);
});
