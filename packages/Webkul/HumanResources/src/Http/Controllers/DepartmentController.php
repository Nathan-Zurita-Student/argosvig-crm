<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\DataGrids\DepartmentDataGrid;
use Webkul\HumanResources\Http\Requests\DepartmentRequest;
use Webkul\HumanResources\Repositories\DepartmentRepository;

class DepartmentController extends Controller
{
    public function __construct(protected DepartmentRepository $departmentRepository) {}

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(DepartmentDataGrid::class)->process();
        }

        return view('human_resources::departments.index');
    }

    public function create(): View
    {
        return view('human_resources::departments.create');
    }

    public function store(DepartmentRequest $request)
    {
        Event::dispatch('human_resources.department.create.before');

        $department = $this->departmentRepository->create($request->validated());

        Event::dispatch('human_resources.department.create.after', $department);

        session()->flash('success', trans('human_resources::app.departments.messages.created'));

        return redirect()->route('admin.human_resources.departments.index');
    }

    public function edit(int $id): View
    {
        $department = $this->departmentRepository->findOrFail($id);

        return view('human_resources::departments.edit', compact('department'));
    }

    public function update(DepartmentRequest $request, int $id)
    {
        Event::dispatch('human_resources.department.update.before', $id);

        $department = $this->departmentRepository->update($request->validated(), $id);

        Event::dispatch('human_resources.department.update.after', $department);

        session()->flash('success', trans('human_resources::app.departments.messages.updated'));

        return redirect()->route('admin.human_resources.departments.index');
    }

    public function destroy(int $id): JsonResponse
    {
        $department = $this->departmentRepository->findOrFail($id);

        if ($department->employees()->exists()) {
            return response()->json([
                'message' => trans('human_resources::app.departments.messages.in-use'),
            ], 422);
        }

        Event::dispatch('human_resources.department.delete.before', $id);

        $this->departmentRepository->delete($id);

        Event::dispatch('human_resources.department.delete.after', $id);

        return response()->json([
            'message' => trans('human_resources::app.departments.messages.deleted'),
        ]);
    }
}
