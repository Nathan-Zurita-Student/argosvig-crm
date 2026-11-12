<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\HumanResources\DataGrids\ServiceContractDataGrid;
use Webkul\HumanResources\Http\Requests\ServiceContractRequest;
use Webkul\HumanResources\Repositories\ServiceContractRepository;
use Webkul\User\Repositories\UserRepository;

class ServiceContractController extends Controller
{
    public function __construct(protected ServiceContractRepository $repository, protected OrganizationRepository $organizationRepository, protected UserRepository $userRepository) {}

    public function index(Request $request): View|JsonResponse
    {
        return $request->ajax() ? datagrid(ServiceContractDataGrid::class)->process() : view('human_resources::contracts.index');
    }

    public function create(): View
    {
        return view('human_resources::contracts.create', $this->formData());
    }

    public function store(ServiceContractRequest $request): RedirectResponse
    {
        $this->repository->create($request->validated());
        session()->flash('success', trans('human_resources::app.contracts.messages.created'));

        return redirect()->route('admin.human_resources.contracts.index');
    }

    public function edit(int $id): View
    {
        return view('human_resources::contracts.edit', [...$this->formData(), 'contract' => $this->repository->findOrFail($id)]);
    }

    public function update(ServiceContractRequest $request, int $id): RedirectResponse
    {
        $this->repository->update($request->validated(), $id);
        session()->flash('success', trans('human_resources::app.contracts.messages.updated'));

        return redirect()->route('admin.human_resources.contracts.index');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->repository->delete($id);

        return response()->json(['message' => trans('human_resources::app.contracts.messages.deleted')]);
    }

    private function formData(): array
    {
        return ['organizations' => $this->organizationRepository->orderBy('name')->all(), 'users' => $this->userRepository->orderBy('name')->all()];
    }
}
