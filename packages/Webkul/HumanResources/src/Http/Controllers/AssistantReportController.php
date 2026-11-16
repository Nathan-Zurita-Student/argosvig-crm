<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Traits\PDFHandler;
use Webkul\HumanResources\Services\AssistantReport;

class AssistantReportController extends Controller
{
    use PDFHandler;

    public function __invoke(Request $request, AssistantReport $report)
    {
        abort_unless(bouncer()->hasPermission('crm-ai'), 401);

        $selection = $request->validate([
            'section' => ['nullable', 'string', 'max:40'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $only = $selection['section'] ?? null;
        $sections = $report->sections($only, $only ? (int) ($selection['page'] ?? 1) : 1);
        $document = [
            'title' => $only ? $sections[0]['title'] : trans('human_resources::reports.overview'),
            'sections' => $sections,
            'only' => $only,
            'issued' => now()->format('d/m/Y H:i'),
        ];

        return $this->downloadPDF(view('human_resources::ai.overview-pdf', $document)->render(), 'argosvig-relatorio-'.($only ?? 'geral'))
            ->header('Cache-Control', 'private, no-store');
    }
}
