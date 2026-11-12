<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\DataGrids\WorkSiteDataGrid;
use Webkul\HumanResources\Http\Requests\WorkSiteRequest;
use Webkul\HumanResources\Repositories\ServiceContractRepository;
use Webkul\HumanResources\Repositories\WorkSiteRepository;

class WorkSiteController extends Controller
{
    public function __construct(protected WorkSiteRepository $repository, protected ServiceContractRepository $contractRepository) {}

    public function index(Request $request): View|JsonResponse
    {
        return $request->ajax() ? datagrid(WorkSiteDataGrid::class)->process() : view('human_resources::work-sites.index');
    }

    public function create(): View
    {
        return view('human_resources::work-sites.create', ['contracts' => $this->contractRepository->orderBy('contract_number')->all()]);
    }

    public function store(WorkSiteRequest $request): RedirectResponse
    {
        $this->repository->create($request->validated());
        session()->flash('success', trans('human_resources::app.work_sites.messages.created'));

        return redirect()->route('admin.human_resources.work_sites.index');
    }

    public function edit(int $id): View
    {
        return view('human_resources::work-sites.edit', ['workSite' => $this->repository->findOrFail($id), 'contracts' => $this->contractRepository->orderBy('contract_number')->all()]);
    }

    public function update(WorkSiteRequest $request, int $id): RedirectResponse
    {
        $this->repository->update($request->validated(), $id);
        session()->flash('success', trans('human_resources::app.work_sites.messages.updated'));

        return redirect()->route('admin.human_resources.work_sites.index');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->repository->delete($id);

        return response()->json(['message' => trans('human_resources::app.work_sites.messages.deleted')]);
    }
}
