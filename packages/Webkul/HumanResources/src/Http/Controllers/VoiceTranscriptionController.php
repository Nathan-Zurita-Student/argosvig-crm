<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\Services\OperationsAi;

class VoiceTranscriptionController extends Controller
{
    private const FORMATS = [
        'audio/mpeg' => 'mp3',
        'audio/x-wav' => 'wav',
        'audio/wav' => 'wav',
        'audio/wave' => 'wav',
        'audio/ogg' => 'ogg',
        'application/ogg' => 'ogg',
        'audio/webm' => 'webm',
        'video/webm' => 'webm',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'video/mp4' => 'm4a',
    ];

    public function __invoke(Request $request, OperationsAi $ai): JsonResponse
    {
        abort_unless(bouncer()->hasPermission('crm-ai') && $ai->transcriptionAvailable(), 401);

        $audio = $request->validate([
            'audio' => ['required', 'file', 'max:8192', 'extensions:wav,mp3,m4a,ogg,webm'],
        ])['audio'];

        $format = self::FORMATS[$audio->getMimeType()] ?? null;

        if ($format === null || strtolower($audio->getClientOriginalExtension()) !== $format) {
            throw ValidationException::withMessages(['audio' => trans('human_resources::operations.invalid_audio')]);
        }

        return response()->json(['transcript' => $ai->transcribe($audio, $format)])
            ->header('Cache-Control', 'private, no-store');
    }
}
