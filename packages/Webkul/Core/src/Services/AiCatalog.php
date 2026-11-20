<?php

namespace Webkul\Core\Services;

/**
 * The AI providers the CRM talks to directly and the models offered for each one.
 *
 * Levels compare the models of the same provider on a 1–5 scale (cost on 1–4), taken
 * from each vendor's model overview in November 2026: OpenAI (developers.openai.com/api/docs/models),
 * Google (ai.google.dev/gemini-api/docs/models) and Anthropic (platform.claude.com/docs).
 * When a vendor ships a new model, add it here; the settings screen also accepts any
 * other model ID typed by the administrator.
 */
final class AiCatalog
{
    public const PROVIDERS = [
        'openai' => [
            'key_url' => 'https://platform.openai.com/api-keys',
            'key_hint' => 'sk-…',
            'transcribes' => true,
            'models' => [
                ['id' => 'gpt-6-astra', 'name' => 'GPT-6 Astra', 'tag' => 'smartest', 'reasoning' => 5, 'speed' => 2, 'cost' => 4],
                ['id' => 'gpt-6.1-sol', 'name' => 'GPT-6.1 Sol', 'tag' => 'recommended', 'reasoning' => 4, 'speed' => 3, 'cost' => 3],
                ['id' => 'gpt-6.1-terra', 'name' => 'GPT-6.1 Terra', 'tag' => 'fast', 'reasoning' => 3, 'speed' => 4, 'cost' => 2],
                ['id' => 'gpt-6-luna', 'name' => 'GPT-6 Luna', 'tag' => 'economic', 'reasoning' => 2, 'speed' => 5, 'cost' => 1],
            ],
            // whisper-1 and gpt-4o-transcribe are deprecated by OpenAI (shutdown in February 2027).
            'audio_models' => [
                ['id' => 'gpt-transcribe', 'name' => 'GPT-Transcribe', 'tag' => 'recommended'],
            ],
        ],
        'gemini' => [
            'key_url' => 'https://aistudio.google.com/apikey',
            'key_hint' => 'AIza…',
            'transcribes' => true,
            'models' => [
                ['id' => 'gemini-4-carbon', 'name' => 'Gemini 4 Carbon', 'tag' => 'smartest', 'reasoning' => 5, 'speed' => 2, 'cost' => 4],
                ['id' => 'gemini-4-argon', 'name' => 'Gemini 4 Argon', 'tag' => null, 'reasoning' => 4, 'speed' => 3, 'cost' => 3],
                ['id' => 'gemini-4-hydrogen', 'name' => 'Gemini 4 Hydrogen', 'tag' => 'recommended', 'reasoning' => 4, 'speed' => 4, 'cost' => 2],
                ['id' => 'gemini-3.8-flash', 'name' => 'Gemini 3.8 Flash', 'tag' => null, 'reasoning' => 3, 'speed' => 4, 'cost' => 2],
                ['id' => 'gemini-3.5-flash-lite', 'name' => 'Gemini 3.5 Flash-Lite', 'tag' => 'economic', 'reasoning' => 2, 'speed' => 5, 'cost' => 1],
            ],
            // Gemini text models take audio directly, so transcription uses one of them.
            'audio_models' => [
                ['id' => 'gemini-3.5-flash-lite', 'name' => 'Gemini 3.5 Flash-Lite', 'tag' => 'recommended'],
                ['id' => 'gemini-3.8-flash', 'name' => 'Gemini 3.8 Flash', 'tag' => null],
                ['id' => 'gemini-4-hydrogen', 'name' => 'Gemini 4 Hydrogen', 'tag' => null],
            ],
        ],
        'anthropic' => [
            'key_url' => 'https://console.anthropic.com/settings/keys',
            'key_hint' => 'sk-ant-…',
            'transcribes' => false,
            'models' => [
                ['id' => 'claude-fable-5-2', 'name' => 'Claude Fable 5.2', 'tag' => 'smartest', 'reasoning' => 5, 'speed' => 2, 'cost' => 4],
                ['id' => 'claude-opus-5-5', 'name' => 'Claude Opus 5.5', 'tag' => null, 'reasoning' => 4, 'speed' => 2, 'cost' => 3],
                ['id' => 'claude-sonnet-5-5', 'name' => 'Claude Sonnet 5.5', 'tag' => 'recommended', 'reasoning' => 3, 'speed' => 4, 'cost' => 2],
                ['id' => 'claude-haiku-5-5', 'name' => 'Claude Haiku 5.5', 'tag' => 'economic', 'reasoning' => 2, 'speed' => 5, 'cost' => 1],
            ],
            'audio_models' => [],
        ],
    ];

    /**
     * Transcription models of a provider; empty for one that does not take audio.
     */
    public static function audioModels(string $provider): array
    {
        return self::PROVIDERS[$provider]['audio_models'] ?? [];
    }

    /**
     * Translation key of a transcription model's description.
     */
    public static function audioNoteKey(string $model): string
    {
        return 'human_resources::ai.audio_notes.'.str_replace(['.', '-'], '_', $model);
    }

    /**
     * Provider keys in display order.
     */
    public static function providers(): array
    {
        return array_keys(self::PROVIDERS);
    }

    public static function models(string $provider): array
    {
        return self::PROVIDERS[$provider]['models'] ?? [];
    }

    /**
     * The model tagged as recommended, used until the administrator picks another one.
     */
    public static function defaultModel(string $provider): ?string
    {
        foreach (self::models($provider) as $model) {
            if ($model['tag'] === 'recommended') {
                return $model['id'];
            }
        }

        return self::models($provider)[0]['id'] ?? null;
    }

    /**
     * Display name of a model, or the raw ID for one typed by the administrator.
     */
    public static function modelName(string $provider, ?string $model): ?string
    {
        foreach (self::models($provider) as $entry) {
            if ($entry['id'] === $model) {
                return $entry['name'];
            }
        }

        return $model;
    }

    /**
     * Translation key of a model's description: IDs carry dots, which translation keys cannot.
     */
    public static function noteKey(string $model): string
    {
        return 'human_resources::ai.notes.'.str_replace(['.', '-'], '_', $model);
    }
}
