<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Services\CrmAi;

class AiConnectionController extends Controller
{
    public function index(CrmAi $ai): View
    {
        $this->authorizeConfiguration();

        return view('human_resources::ai.connection', [
            'configured' => $ai->configured(), 'enabled' => $ai->enabled(), 'provider' => $ai->provider(),
            'model' => $ai->model(), 'audioModel' => $ai->audioModel(), 'audioProvider' => $ai->audioProvider(),
        ]);
    }

    public function test(CrmAi $ai): RedirectResponse
    {
        $this->authorizeConfiguration();
        $message = 'key_missing';

        if ($ai->configured()) {
            try {
                $message = 'disabled';
                if ($ai->provider() === 'openrouter') {
                    $key = $ai->openRouterKeyStatus();
                    $message = match ($key->status()) {
                        200 => is_array($key->json('data')) ? 'key_ok' : 'provider_error',
                        401, 403 => 'invalid_key',
                        429 => 'rate_limited',
                        default => 'provider_error',
                    };
                }

                if (in_array($message, ['disabled', 'key_ok'], true) && $ai->enabled()) {
                    $test = $ai->chat('This is a connection test.', 'Respond with OK.', 512);
                    $message = $test->successful() && filled($ai->text($test))
                        ? 'connected'
                        : match ($test->status()) {
                            401, 403 => 'invalid_key',
                            402 => 'billing',
                            429 => $test->json('error.code') === 'insufficient_quota' ? 'billing' : 'rate_limited',
                            400, 404 => 'model_unavailable',
                            default => 'provider_error',
                        };
                }
            } catch (ConnectionException) {
                $message = 'provider_error';
            }
        }

        return redirect()->route('admin.ai.connection')->with('ai_status', $message);
    }

    private function authorizeConfiguration(): void
    {
        abort_unless(bouncer()->hasPermission('configuration'), 401);
    }
}
