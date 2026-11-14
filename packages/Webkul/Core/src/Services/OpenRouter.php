<?php

namespace Webkul\Core\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenRouter
{
    private const BASE = 'https://openrouter.ai/api/v1';

    public function keyStatus(?string $key): Response
    {
        return $this->client($key)->get(self::BASE.'/key');
    }

    public function chat(?string $key, string $model, array $messages, int $maxTokens): Response
    {
        return $this->client($key)->post(self::BASE.'/chat/completions', [
            'model' => $model, 'messages' => $messages, 'max_tokens' => $maxTokens,
            'provider' => ['data_collection' => 'deny', 'zdr' => true],
        ]);
    }

    public function transcribe(?string $key, string $model, string $contents, string $format): Response
    {
        return $this->client($key)->timeout(60)->post(self::BASE.'/audio/transcriptions', [
            'model' => $model, 'language' => 'pt',
            'input_audio' => ['format' => $format, 'data' => base64_encode($contents)],
            'provider' => ['data_collection' => 'deny', 'zdr' => true],
        ]);
    }

    private function client(?string $key): PendingRequest
    {
        return Http::acceptJson()->withToken($key ?? '')
            ->connectTimeout(5)->timeout(25)->withoutRedirecting();
    }
}
