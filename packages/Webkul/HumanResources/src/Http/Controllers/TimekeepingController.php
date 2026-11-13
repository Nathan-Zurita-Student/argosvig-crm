<?php

namespace Webkul\HumanResources\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\Repositories\EmployeeRepository;
use Webkul\HumanResources\Services\TimekeepingImporter;

class TimekeepingController extends Controller
{
    public function index(Request $request, EmployeeRepository $employees): View
    {
        $this->authorizeScope();

        [$query, $from, $to] = $this->filteredEntries($request);

        return view('human_resources::timekeeping.index', [
            'from' => $from, 'to' => $to,
            'employees' => $employees->getModel()->newQuery()->withTrashed()->orderBy('full_name')->get(['id', 'full_name', 'employee_number']),
            'entries' => $query->orderByDesc('entries.occurred_at')->orderByDesc('entries.id')->paginate(30)->withQueryString(),
            'batches' => DB::table('hr_timekeeping_batches')->latest('id')->limit(5)->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeScope();
        [$query] = $this->filteredEntries($request);

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['matricula', 'colaborador', 'data_hora', 'origem'], ';', '"', '');

            foreach ($query->lazyById(500, 'entries.id', 'id') as $entry) {
                fputcsv($stream, [
                    $this->csvCell($entry->employee_number),
                    $this->csvCell($entry->full_name),
                    CarbonImmutable::parse($entry->occurred_at, 'UTC')->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                    $entry->source,
                ], ';', '"', '');
            }

            fclose($stream);
        }, 'marcacoes-de-ponto.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /**
     * @return array{Builder, CarbonImmutable, CarbonImmutable}
     */
    private function filteredEntries(Request $request): array
    {
        $filters = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:hr_employees,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $to = CarbonImmutable::parse($filters['to'] ?? today()->toDateString(), config('app.timezone'));
        $from = isset($filters['from'])
            ? CarbonImmutable::parse($filters['from'], config('app.timezone'))
            : $to->startOfMonth();

        if (! isset($filters['to']) && $from->gt($to)) {
            $to = $from;
        }
        $query = DB::table('hr_timekeeping_entries as entries')->join('hr_employees as employee', 'employee.id', '=', 'entries.employee_id')
            ->where('entries.occurred_at', '>=', $from->utc()->format('Y-m-d H:i:s'))
            ->where('entries.occurred_at', '<', $to->addDay()->utc()->format('Y-m-d H:i:s'))
            ->select('entries.*', 'employee.full_name', 'employee.employee_number');

        if (! empty($filters['employee_id'])) {
            $query->where('entries.employee_id', $filters['employee_id']);
        }

        return [$query, $from, $to];
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^(?:[\t\r\n]|[\s\x{FEFF}]*[=+\-@＝＋－＠])/u', $value) ? "'".$value : $value;
    }

    public function template(): StreamedResponse
    {
        $this->authorizeScope();
        abort_unless(bouncer()->hasPermission('timekeeping'), 401);

        return response()->streamDownload(function () {
            echo "employee_code;occurred_at;external_id\r\n";
        }, 'modelo-marcacoes.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request, TimekeepingImporter $importer): RedirectResponse
    {
        $this->authorizeScope();
        abort_unless(bouncer()->hasPermission('timekeeping'), 401);

        $request->validate(['file' => ['required', 'file', 'extensions:csv', 'max:5120']]);
        $rows = $importer->readCsv($request->file('file')->getPathname());
        $result = $importer->import($rows, 'alterdata_csv', auth()->guard('user')->id());

        return redirect()->route('admin.human_resources.timekeeping.index')
            ->with('success', trans('human_resources::workforce.imported', $result));
    }

    private function authorizeScope(): void
    {
        abort_unless(auth()->guard('user')->user()->view_permission === 'global', 401);
    }
}
