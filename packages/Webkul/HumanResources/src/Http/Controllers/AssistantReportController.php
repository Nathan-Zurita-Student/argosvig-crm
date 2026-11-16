<?php

namespace Webkul\HumanResources\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Frame;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Traits\PDFHandler;
use Webkul\HumanResources\Services\AssistantReport;
use Webkul\HumanResources\Services\IncidentReportFilters;

class AssistantReportController extends Controller
{
    use PDFHandler;

    public function __invoke(Request $request, AssistantReport $report)
    {
        abort_unless(bouncer()->hasPermission('crm-ai'), 401);

        $selection = $request->validate([
            'section' => ['nullable', 'string', 'max:40'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            ...IncidentReportFilters::rules(),
        ]);

        $only = $selection['section'] ?? null;
        $filters = array_intersect_key($selection, IncidentReportFilters::rules());
        $sections = $report->sections($only, $only ? (int) ($selection['page'] ?? 1) : 1, $filters);
        $document = [
            'title' => match ($only) {
                null => trans('human_resources::reports.overview'),
                'incidents' => trans('human_resources::reports.incidents_title'),
                default => $sections[0]['title'],
            },
            'sections' => $sections,
            'only' => $only,
            'priorities' => $only ? [] : $report->priorities(),
            'filterLabels' => IncidentReportFilters::describe($filters),
            'filters' => $filters,
            'markContinuations' => ! in_array(app()->getLocale(), ['ar', 'he'], true),
            'issued' => now()->format('d/m/Y H:i'),
        ];

        $html = view('human_resources::ai.overview-pdf', $document)->render();
        $filename = 'argosvig-relatorio-'.($only ?? 'geral');

        return ($document['markContinuations'] ? $this->downloadReport($html, $filename) : $this->downloadPDF($html, $filename))
            ->header('Cache-Control', 'private, no-store');
    }

    private function downloadReport(string $html, string $filename)
    {
        $pdf = Pdf::loadHTML($this->adjustArabicAndPersianContent($html))->setPaper('A4');
        $seen = [];

        // Dompdf clones the table headers when it splits a table across pages.
        $pdf->getDomPDF()->setCallbacks([[
            'event' => 'begin_frame',
            'f' => static function (Frame $frame) use (&$seen): void {
                $node = $frame->get_node();
                if ($node instanceof \DOMElement && $node->hasAttribute('data-report-continuation')) {
                    $section = $node->getAttribute('data-report-continuation');
                    if (! isset($seen[$section])) {
                        $frame->get_style()->visibility = 'hidden';
                        $seen[$section] = true;
                    }
                }
            },
        ]]);

        return $pdf->download($filename.'.pdf');
    }
}
