<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Services\OpenRouter;

class AiConnectionController extends Controller
{
    public function index(OpenRouter $router): View
    {
        $this->authorizeConfiguration();

        return view('human_resources::ai.connection', [
            'configured' => $router->configured(), 'enabled' => $router->enabled(),
            'model' => $router->model(), 'audioModel' => $router->audioModel(),
        ]);
    }

    public function test(OpenRouter $router): RedirectResponse
    {
        $this->authorizeConfiguration();
        $message = 'key_missing';

        if ($router->configured()) {
            try {
                $key = $router->keyStatus();
                $message = match ($key->status()) {
                    200 => is_array($key->json('data')) ? 'key_ok' : 'provider_error',
                    401, 403 => 'invalid_key',
                    429 => 'rate_limited',
                    default => 'provider_error',
                };

                if ($message === 'key_ok' && $router->enabled()) {
                    $test = $router->chat([['role' => 'user', 'content' => 'Respond with OK.']], 24);
                    $message = $test->successful() && filled($test->json('choices.0.message.content'))
                        ? 'connected'
                        : match ($test->status()) {
                            401, 403 => 'invalid_key',
                            402 => 'billing',
                            429 => 'rate_limited',
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
