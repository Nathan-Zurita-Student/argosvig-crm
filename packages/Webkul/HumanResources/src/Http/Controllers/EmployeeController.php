<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\DataGrids\EmployeeDataGrid;
use Webkul\HumanResources\Http\Requests\EmployeeRequest;
use Webkul\HumanResources\Repositories\DepartmentRepository;
use Webkul\HumanResources\Repositories\EmployeeRepository;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeRepository $employeeRepository,
        protected DepartmentRepository $departmentRepository,
    ) {}

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(EmployeeDataGrid::class)->process();
        }

        return view('human_resources::employees.index');
    }

    public function create(): View
    {
        $departments = $this->departmentRepository
            ->orderBy('name')
            ->findWhere(['is_active' => true]);

        return view('human_resources::employees.create', compact('departments'));
    }

    public function store(EmployeeRequest $request)
    {
        Event::dispatch('human_resources.employee.create.before');

        $employee = $this->employeeRepository->create($request->validated());

        Event::dispatch('human_resources.employee.create.after', $employee);

        session()->flash('success', trans('human_resources::app.employees.messages.created'));

        return redirect()->route('admin.human_resources.employees.index');
    }

    public function edit(int $id): View
    {
        $employee = $this->employeeRepository->findOrFail($id);
        $departments = $this->departmentRepository->orderBy('name')->all();

        return view('human_resources::employees.edit', compact('departments', 'employee'));
    }

    public function update(EmployeeRequest $request, int $id)
    {
        Event::dispatch('human_resources.employee.update.before', $id);

        $employee = $this->employeeRepository->update($request->validated(), $id);

        Event::dispatch('human_resources.employee.update.after', $employee);

        session()->flash('success', trans('human_resources::app.employees.messages.updated'));

        return redirect()->route('admin.human_resources.employees.index');
    }

    public function destroy(int $id): JsonResponse
    {
        Event::dispatch('human_resources.employee.delete.before', $id);

        $this->employeeRepository->delete($id);

        Event::dispatch('human_resources.employee.delete.after', $id);

        return response()->json([
            'message' => trans('human_resources::app.employees.messages.deleted'),
        ]);
    }
}
