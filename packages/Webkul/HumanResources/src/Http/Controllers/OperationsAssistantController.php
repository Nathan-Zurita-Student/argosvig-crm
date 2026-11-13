<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\Services\OperationsAi;
use Webkul\HumanResources\Services\OperationsBriefing;

class OperationsAssistantController extends Controller
{
    public function index(Request $request, OperationsBriefing $briefing, OperationsAi $ai): View
    {
        abort_unless(OperationsBriefing::allowed(), 401);

        return view('human_resources::operations.index', $this->data($request, $briefing, $ai));
    }

    public function ask(Request $request, OperationsBriefing $briefing, OperationsAi $ai): View
    {
        abort_unless(OperationsBriefing::allowed() && bouncer()->hasPermission('operations-assistant.ai') && $ai->available(), 401);

        $question = $request->validate(['question' => ['required', 'string', 'max:500']])['question'];
        $data = $this->data($request, $briefing, $ai);
        $filters = ['from' => $data['from'], 'to' => $data['to'], 'site' => $data['site']?->id];
        foreach (['incidents', 'shifts'] as $section) {
            $data[$section]?->withPath(route('admin.operations.index'))->appends($filters);
        }
        $facts = [
            'periodo' => [$data['from'], $data['to']],
            'posto_filtrado' => $data['site'] !== null,
            'ocorrencias_do_contrato_quando_filtrado' => true,
            'ocorrencias' => $data['incidents']?->getCollection()->map(fn ($incident) => [
                'codigo' => $incident->code, 'data' => $incident->occurred_at->toDateTimeString(),
                'gravidade' => $incident->severity, 'situacao' => $incident->status,
            ])->values()->all(),
            'escalas' => $data['shifts']?->getCollection()->map(fn ($shift) => [
                'referencia' => 'Escala '.$shift->id, 'posto' => 'Posto '.$shift->work_site_id,
                'inicio' => $shift->starts_at->toDateTimeString(), 'fim' => $shift->ends_at->toDateTimeString(), 'situacao' => $shift->status,
            ])->values()->all(),
            'total_ocorrencias' => $data['incidents']?->total(),
            'total_escalas' => $data['shifts']?->total(),
        ];

        try {
            $data['answer'] = $ai->summarize($question, $facts);
        } catch (ValidationException $exception) {
            $data['assistantError'] = $exception->validator->errors()->first();
        }

        return view('human_resources::operations.index', $data + ['question' => $question]);
    }

    private function data(Request $request, OperationsBriefing $briefing, OperationsAi $ai): array
    {
        $filters = $request->validate([
            'site' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = isset($filters['from']) ? Carbon::parse($filters['from']) : today();
        $to = isset($filters['to']) ? Carbon::parse($filters['to']) : $from->copy()->addDay();
        if ($to->lt($from) || $from->diffInDays($to) > 30) {
            throw ValidationException::withMessages(['to' => trans('human_resources::operations.period_limit')]);
        }

        return [
            ...$briefing->report(isset($filters['site']) ? (int) $filters['site'] : null, $from, $to->copy()->addDay()),
            'sites' => $briefing->sites(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'canAsk' => bouncer()->hasPermission('operations-assistant.ai') && $ai->available(),
        ];
    }
}
