<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Traits\PDFHandler;
use Webkul\HumanResources\Models\ServiceContract;
use Webkul\HumanResources\Repositories\ServiceContractRepository;
use Webkul\HumanResources\Services\OperationsAi;
use Webkul\HumanResources\Services\OperationsBriefing;
use Webkul\HumanResources\Services\ShiftCoverage;
use Webkul\HumanResources\Services\WorkforceReview;

class OperationsAssistantController extends Controller
{
    use PDFHandler;

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
        $filters = ['from' => $data['from'], 'to' => $data['to'], 'site' => $data['site']?->id, 'contract' => $data['contextContract']?->id];
        foreach (['incidents', 'shifts', 'punches', 'documents', 'contracts'] as $section) {
            $data[$section]?->withPath(route('admin.operations.index'))->appends($filters);
        }
        $facts = [
            'periodo' => [$data['from'], $data['to']],
            'posto_filtrado' => $data['site'] !== null,
            'contrato_filtrado' => $data['contextContract'] !== null,
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
            'total_lacunas_48h' => $data['canSeeCoverage'] ? $data['gapCount'] : null,
            'lacunas_48h' => ! $data['canSeeCoverage'] ? null : array_map(fn ($gap) => [
                'posto' => 'Posto '.$gap['site']->id, 'inicio' => $gap['from']->toDateTimeString(),
                'fim' => $gap['to']->toDateTimeString(), 'confirmados' => $gap['confirmed'], 'necessarios' => $gap['required'],
            ], $data['gaps']),
            'conferencia_de_ponto' => $data['punches']?->getCollection()->map(fn ($row) => [
                'referencia' => 'Escala '.$row['shift']->id, 'resultado' => $row['result'],
            ])->values()->all(),
            'documentos_a_vencer' => $data['documents']?->total(),
            'contratos_a_vencer' => $data['contracts']?->getCollection()->map(fn ($contract) => [
                'referencia' => 'Contrato '.$contract->id, 'vence_em' => $contract->ends_at->toDateString(),
            ])->values()->all(),
        ];

        try {
            $data['answer'] = $ai->summarize($question, $facts);
        } catch (ValidationException $exception) {
            $data['assistantError'] = $exception->validator->errors()->first();
        }

        return view('human_resources::operations.index', $data + ['question' => $question]);
    }

    public function coverage(int $site, Request $request, OperationsBriefing $briefing, ShiftCoverage $coverage): View
    {
        abort_unless(OperationsBriefing::allowed() && ShiftCoverage::canSuggest(), 401);
        $filters = $request->validate([
            'start' => ['required', 'date_format:Y-m-d H:i:s'],
            'end' => ['required', 'date_format:Y-m-d H:i:s', 'after:start'],
        ]);
        $workSite = $briefing->sites()->firstWhere('id', $site);
        abort_unless($workSite, 404);
        $from = Carbon::parse($filters['start']);
        $to = Carbon::parse($filters['end']);
        $now = now();
        abort_if($to->lte($now) || $to->gt($now->copy()->addHours(48)), 404);
        $from = max($now, $from);
        $current = $coverage->gaps(collect([$workSite]), $from, $to);
        $gap = count($current) === 1 && $current[0]['from']->timestamp === $from->timestamp && $current[0]['to']->timestamp === $to->timestamp ? $current[0] : null;

        return view('human_resources::operations.coverage', [
            'site' => $workSite, 'gap' => $gap, 'candidates' => $gap ? $coverage->candidates($from, $to) : collect(),
        ]);
    }

    public function exportContract(Request $request, OperationsBriefing $briefing): Response|StreamedResponse
    {
        abort_unless(OperationsBriefing::allowed() && bouncer()->hasPermission('human-resources.contracts'), 401);
        $request->validate(['contract' => ['required', 'integer', 'min:1']]);
        [$filters, $from, $to] = $this->filters($request);
        $contract = $this->contract((int) $filters['contract']);
        $data = $briefing->report(null, $from, $to->copy()->addDay(), $contract->id) + [
            'contextContract' => $contract, 'from' => $from, 'to' => $to,
        ];

        $response = $this->downloadPDF(view('human_resources::operations.contract-report', $data)->render(), 'relatorio-operacional');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function data(Request $request, OperationsBriefing $briefing, OperationsAi $ai): array
    {
        [$filters, $from, $to] = $this->filters($request);

        $end = $to->copy()->addDay();
        $contractId = isset($filters['contract']) ? (int) $filters['contract'] : null;
        $report = $briefing->report(isset($filters['site']) ? (int) $filters['site'] : null, $from, $end, $contractId);
        $contextContract = $contractId ? $this->contract($contractId) : null;
        $sites = $briefing->sites();
        if ($contextContract) {
            $sites = $sites->where('service_contract_id', $contextContract->id);
        }
        $now = now();
        $gaps = ShiftCoverage::allowed() ? app(ShiftCoverage::class)->gaps(
            $report['site'] ? $sites->where('id', $report['site']->id) : $sites,
            max($now, $from), min($now->copy()->addHours(48), $end),
        ) : [];

        return [
            ...$report,
            ...app(WorkforceReview::class)->review($report['site'], $from, $end, $contractId),
            'sites' => $sites,
            'contextContract' => $contextContract,
            'gaps' => array_slice($gaps, 0, 15),
            'gapCount' => count($gaps),
            'canSeeCoverage' => ShiftCoverage::allowed(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'canAsk' => bouncer()->hasPermission('operations-assistant.ai') && $ai->available(),
        ];
    }

    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'site' => ['nullable', 'integer', 'min:1'],
            'contract' => ['nullable', 'integer', 'min:1', 'prohibits:site'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = isset($filters['from']) ? Carbon::parse($filters['from']) : today();
        $to = isset($filters['to']) ? Carbon::parse($filters['to']) : $from->copy()->addDays(2);

        if ($to->lt($from) || $from->diffInDays($to) > 30) {
            throw ValidationException::withMessages(['to' => trans('human_resources::operations.period_limit')]);
        }

        return [$filters, $from, $to];
    }

    private function contract(int $id): ServiceContract
    {
        return app(ServiceContractRepository::class)->getModel()->newQuery()->select('id', 'contract_number', 'title')->findOrFail($id);
    }
}
