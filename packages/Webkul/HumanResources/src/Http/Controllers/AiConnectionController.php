<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Services\CrmAi;

class AiConnectionController extends Controller
{
    /**
     * The connection is now checked from the AI settings screen; the old address leads there.
     */
    public function index(): RedirectResponse
    {
        $this->authorizeConfiguration();

        return redirect()->route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'crm_ai']);
    }

    /**
     * Sends a short synthetic message, with no CRM data, to the saved provider and model.
     */
    public function test(Request $request, CrmAi $ai): JsonResponse|RedirectResponse
    {
        $this->authorizeConfiguration();

        $status = match (true) {
            ! $ai->configured() => 'key_missing',
            ! $ai->enabled() => 'disabled',
            default => $this->check($ai),
        };

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $status,
                'ok' => $status === 'connected',
                'message' => trans('human_resources::ai.status.'.$status),
                'model' => $ai->label(),
            ]);
        }

        return redirect()->route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'crm_ai'])->with('ai_status', $status);
    }

    private function check(CrmAi $ai): string
    {
        try {
            $test = $ai->chat('This is a connection test.', 'Respond with OK.', 512);
        } catch (ConnectionException) {
            return 'provider_error';
        }

        if ($test->successful() && filled($ai->text($test))) {
            return 'connected';
        }

        return match ($test->status()) {
            401, 403 => 'invalid_key',
            402 => 'billing',
            429 => $test->json('error.code') === 'insufficient_quota' ? 'billing' : 'rate_limited',
            400, 404 => 'model_unavailable',
            default => 'provider_error',
        };
    }

    private function authorizeConfiguration(): void
    {
        abort_unless(bouncer()->hasPermission('configuration'), 401);
    }
}
