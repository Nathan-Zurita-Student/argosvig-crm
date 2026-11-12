<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\DataGrids\ShiftAssignmentDataGrid;
use Webkul\HumanResources\Http\Requests\ShiftAssignmentRequest;
use Webkul\HumanResources\Repositories\EmployeeRepository;
use Webkul\HumanResources\Repositories\ShiftAssignmentRepository;
use Webkul\HumanResources\Repositories\WorkSiteRepository;

class ShiftAssignmentController extends Controller
{
    public function __construct(protected ShiftAssignmentRepository $repository, protected WorkSiteRepository $workSiteRepository, protected EmployeeRepository $employeeRepository) {}

    public function index(Request $request): View|JsonResponse
    {
        return $request->ajax() ? datagrid(ShiftAssignmentDataGrid::class)->process() : view('human_resources::shifts.index');
    }

    public function create(): View
    {
        return view('human_resources::shifts.create', $this->formData());
    }

    public function store(ShiftAssignmentRequest $request): RedirectResponse
    {
        $this->repository->create($request->validated());
        session()->flash('success', trans('human_resources::app.shifts.messages.created'));

        return redirect()->route('admin.human_resources.shifts.index');
    }

    public function edit(int $id): View
    {
        return view('human_resources::shifts.edit', [...$this->formData(), 'shift' => $this->repository->findOrFail($id)]);
    }

    public function update(ShiftAssignmentRequest $request, int $id): RedirectResponse
    {
        $this->repository->update($request->validated(), $id);
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
}
