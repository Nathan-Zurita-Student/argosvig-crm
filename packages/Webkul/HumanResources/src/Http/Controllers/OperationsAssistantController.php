<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\Services\OperationsBriefing;

class OperationsAssistantController extends Controller
{
    public function index(Request $request, OperationsBriefing $briefing): View
    {
        abort_unless(OperationsBriefing::allowed(), 401);

        $filters = $request->validate([
            'site' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = isset($filters['from']) ? Carbon::parse($filters['from']) : today();
        $to = isset($filters['to']) ? Carbon::parse($filters['to']) : $from->copy()->addDay();
        abort_if($to->lt($from) || $from->diffInDays($to) > 30, 422, trans('human_resources::operations.period_limit'));

        return view('human_resources::operations.index', [
            ...$briefing->report(isset($filters['site']) ? (int) $filters['site'] : null, $from, $to->copy()->addDay()),
            'sites' => $briefing->sites(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }
}
