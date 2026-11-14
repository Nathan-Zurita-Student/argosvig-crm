<?php

namespace Webkul\Core\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenRouter
{
    public const KEY = 'general.magic_ai.settings.api_key';

    public const MODEL = 'openai/gpt-4.1-mini';

    public const AUDIO_MODEL = 'openai/whisper-1';

    private const BASE = 'https://openrouter.ai/api/v1';

    public function configured(): bool
    {
        return filled($this->key());
    }

    public function enabled(): bool
    {
        $enabled = core()->getConfigData('general.magic_ai.settings.enable') ?? config('operations.ai_enabled') ?? true;

        return $this->configured() && (bool) $enabled;
    }

    public function model(): string
    {
        return core()->getConfigData('general.magic_ai.settings.other_model')
            ?: core()->getConfigData('general.magic_ai.settings.model')
            ?: self::MODEL;
    }

    public function audioModel(): string
    {
        return config('operations.transcription_model') ?: self::AUDIO_MODEL;
    }

    public function keyStatus(): Response
    {
        return $this->client()->get(self::BASE.'/key');
    }

    public function chat(array $messages, int $maxTokens = 650): Response
    {
        return $this->client()->post(self::BASE.'/chat/completions', [
            'model' => $this->model(), 'messages' => $messages, 'max_tokens' => $maxTokens,
            'provider' => ['data_collection' => 'deny', 'zdr' => true],
        ]);
    }

    public function transcribe(string $contents, string $format): Response
    {
        return $this->client()->timeout(60)->post(self::BASE.'/audio/transcriptions', [
            'model' => $this->audioModel(), 'language' => 'pt',
            'input_audio' => ['format' => $format, 'data' => base64_encode($contents)],
            'provider' => ['data_collection' => 'deny', 'zdr' => true],
        ]);
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()->withToken($this->key() ?? '')
            ->connectTimeout(5)->timeout(25)->withoutRedirecting();
    }

    private function key(): ?string
    {
        return config('services.openrouter.key') ?: core()->getConfigData(self::KEY);
    }
}
