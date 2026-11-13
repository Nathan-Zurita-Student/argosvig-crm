<?php

namespace Webkul\HumanResources\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\HumanResources\Models\WorkSite;
use Webkul\HumanResources\Repositories\EmployeeDocumentRepository;
use Webkul\HumanResources\Repositories\ServiceContractRepository;
use Webkul\HumanResources\Repositories\ShiftAssignmentRepository;

class WorkforceReview
{
    public function __construct(
        private ShiftAssignmentRepository $shifts,
        private EmployeeDocumentRepository $documents,
        private ServiceContractRepository $contracts,
    ) {}

    public function review(?WorkSite $site, Carbon $from, Carbon $to, ?int $contractId = null): array
    {
        $punches = null;
        if (ShiftCoverage::allowed() && bouncer()->hasPermission('timekeeping')) {
            $punches = $this->shifts->getModel()->newQuery()->with('workSite:id,name')
                ->whereHas('workSite')->when($site, fn ($query) => $query->where('work_site_id', $site->id))
                ->when($contractId, fn ($query) => $query->whereHas('workSite', fn ($workSite) => $workSite->where('service_contract_id', $contractId)))
                ->whereIn('status', ['confirmed', 'completed'])->where('ends_at', '>', $from)
                ->where('starts_at', '<', $to)->where('ends_at', '<=', now()->subMinutes(30))
                ->orderByDesc('ends_at')->orderByDesc('id')
                ->paginate(15, ['id', 'work_site_id', 'employee_id', 'starts_at', 'ends_at'], 'punches')->withQueryString();

            $rows = $punches->getCollection();

            if ($rows->isNotEmpty()) {
                $first = $rows->min('starts_at')->copy()->subMinutes(30)->utc();
                $last = $rows->max('ends_at')->copy()->addMinutes(30)->utc();
                $marks = DB::table('hr_timekeeping_entries')->whereIn('employee_id', $rows->pluck('employee_id')->unique())
                    ->whereBetween('occurred_at', [$first, $last])->get(['employee_id', 'occurred_at'])->groupBy('employee_id');

                $punches->through(function ($shift) use ($marks) {
                    $times = collect($marks->get($shift->employee_id, []))->map(fn ($mark) => Carbon::parse($mark->occurred_at, 'UTC')->timestamp);
                    $matches = fn (Carbon $edge) => $times->filter(fn ($time) => abs($time - $edge->timestamp) <= 1800)->values();
                    $entry = $matches($shift->starts_at);
                    $exit = $matches($shift->ends_at);
                    $result = match (true) {
                        $entry->isEmpty() && $exit->isEmpty() => 'missing_both',
                        $entry->isEmpty() => 'missing_start',
                        $exit->isEmpty() => 'missing_end',
                        $entry->merge($exit)->unique()->count() < 2 => 'single_mark',
                        default => 'matched',
                    };

                    return ['shift' => $shift, 'result' => $result];
                });
            }
        }

        $documents = null;
        $personal = bouncer()->hasPermission('human-resources.document-center');
        $shared = bouncer()->hasPermission('human-resources.shared-documents');
        $today = today()->toDateString();
        $limit = today()->addDays(30)->toDateString();

        if (bouncer()->hasPermission('document-center') && ($personal || $shared)) {
            $documents = $this->documents->getModel()->newQuery()->whereBetween('expires_at', [$today, $limit])
                ->when($site, fn ($query) => $site->service_contract_id ? $query->whereHas('contracts', fn ($contracts) => $contracts->whereKey($site->service_contract_id)) : $query->whereRaw('1 = 0'))
                ->when($contractId, fn ($query) => $query->whereHas('contracts', fn ($contracts) => $contracts->whereKey($contractId)))
                ->when(! $personal, fn ($query) => $query->whereNull('employee_id'))
                ->when(! $shared, fn ($query) => $query->whereNotNull('employee_id'))
                ->orderBy('expires_at')->orderBy('id')->paginate(10, ['id', 'type', 'expires_at'], 'documents')->withQueryString();
        }

        $contracts = bouncer()->hasPermission('human-resources.contracts')
            ? $this->contracts->getModel()->newQuery()->expiring()
                ->when($site, fn ($query) => $query->whereKey($site->service_contract_id ?? 0))
                ->when($contractId, fn ($query) => $query->whereKey($contractId))
                ->orderBy('ends_at')->orderBy('id')->paginate(10, ['id', 'contract_number', 'ends_at'], 'contracts')->withQueryString()
            : null;

        return compact('punches', 'documents', 'contracts');
    }
}
