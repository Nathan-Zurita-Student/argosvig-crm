<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\DataGrids\ShiftAssignmentDataGrid;
use Webkul\HumanResources\Http\Requests\ShiftAssignmentRequest;
use Webkul\HumanResources\Repositories\EmployeeRepository;
use Webkul\HumanResources\Repositories\ShiftAssignmentRepository;
use Webkul\HumanResources\Repositories\WorkSiteRepository;
use Webkul\HumanResources\Services\OperationsBriefing;
use Webkul\HumanResources\Services\ShiftCoverage;

class ShiftAssignmentController extends Controller
{
    public function __construct(protected ShiftAssignmentRepository $repository, protected WorkSiteRepository $workSiteRepository, protected EmployeeRepository $employeeRepository) {}

    public function index(Request $request): View|JsonResponse
    {
        return $request->ajax() ? datagrid(ShiftAssignmentDataGrid::class)->process() : view('human_resources::shifts.index');
    }

    public function create(Request $request): View
    {
        $suggestion = null;

        if ($request->boolean('coverage')) {
            $this->authorizeCoverage();
            $data = $request->validate([
                'work_site_id' => ['required', 'integer', 'exists:hr_work_sites,id'],
                'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
                'starts_at' => ['required', 'date_format:Y-m-d H:i:s'],
                'ends_at' => ['required', 'date_format:Y-m-d H:i:s', 'after:starts_at'],
            ]);
            $suggestion = $this->repository->makeModel()->fill($data);
        }

        return view('human_resources::shifts.create', $this->formData() + ['suggestion' => $suggestion]);
    }

    public function store(ShiftAssignmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $data) {
            $this->employeeRepository->getModel()->newQuery()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();

            if ($request->boolean('coverage')) {
                $this->authorizeCoverage();
                $this->validateCoverage($data);
            }

            $this->repository->create($data);
        }, 3);
        session()->flash('success', trans('human_resources::app.shifts.messages.created'));

        return redirect()->route('admin.human_resources.shifts.index');
    }

    public function edit(int $id): View
    {
        return view('human_resources::shifts.edit', [...$this->formData(), 'shift' => $this->repository->findOrFail($id)]);
    }

    public function update(ShiftAssignmentRequest $request, int $id): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data, $id) {
            $this->employeeRepository->getModel()->newQuery()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $this->repository->update($data, $id);
        }, 3);
        session()->flash('success', trans('human_resources::app.shifts.messages.updated'));

        return redirect()->route('admin.human_resources.shifts.index');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->repository->delete($id);

        return response()->json(['message' => trans('human_resources::app.shifts.messages.deleted')]);
    }

    private function formData(): array
    {
        return ['workSites' => $this->workSiteRepository->orderBy('name')->all(), 'employees' => $this->employeeRepository->orderBy('full_name')->all()];
    }

    private function authorizeCoverage(): void
    {
        abort_unless(OperationsBriefing::allowed() && ShiftCoverage::canSuggest(), 401);
    }

    private function validateCoverage(array $data): void
    {
        $from = Carbon::parse($data['starts_at']);
        $to = Carbon::parse($data['ends_at']);
        $site = $this->workSiteRepository->getModel()->newQuery()->where('status', 'active')->find($data['work_site_id']);

        if (! $site || $to->lte(now()) || ! app(ShiftCoverage::class)->canCover((int) $data['employee_id'], $from, $to)) {
            throw ValidationException::withMessages(['employee_id' => trans('human_resources::operations.coverage_updated')]);
        }
    }
}
