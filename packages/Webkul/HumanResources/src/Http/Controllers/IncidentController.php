<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\DataGrids\IncidentDataGrid;
use Webkul\HumanResources\Http\Requests\IncidentRequest;
use Webkul\HumanResources\Repositories\EmployeeRepository;
use Webkul\HumanResources\Repositories\IncidentRepository;
use Webkul\HumanResources\Repositories\ServiceContractRepository;
use Webkul\User\Repositories\UserRepository;

class IncidentController extends Controller
{
    public function __construct(protected IncidentRepository $repository, protected EmployeeRepository $employeeRepository, protected ServiceContractRepository $contractRepository, protected UserRepository $userRepository) {}

    public function index(Request $request): View|JsonResponse
    {
        return $request->ajax() ? datagrid(IncidentDataGrid::class)->process() : view('human_resources::incidents.index');
    }

    public function create(): View
    {
        $draft = session()->pull('incident_draft');

        return view('human_resources::incidents.create', $this->formData() + [
            'draft' => $draft ? $this->repository->makeModel()->fill($draft) : null,
        ]);
    }

    public function show(int $id): View
    {
        $incident = $this->repository->with(['employee', 'serviceContract.organization', 'assignee', 'reporter'])->findOrFail($id);

        return view('human_resources::incidents.show', compact('incident'));
    }

    public function store(IncidentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] = $this->nextCode();
        $data['reported_by'] = auth()->guard('user')->id();
        if (in_array($data['status'], ['resolved', 'closed'], true)) {
            $data['resolved_at'] = now();
        }
        $incident = $this->repository->create($data);
        session()->flash('success', trans('human_resources::app.incidents.messages.created'));

        return redirect()->route('admin.human_resources.incidents.show', $incident->id);
    }

    public function edit(int $id): View
    {
        return view('human_resources::incidents.edit', [...$this->formData(), 'incident' => $this->repository->findOrFail($id)]);
    }

    public function update(IncidentRequest $request, int $id): RedirectResponse
    {
        $data = $request->validated();
        $data['resolved_at'] = in_array($data['status'], ['resolved', 'closed'], true) ? now() : null;
        $this->repository->update($data, $id);
        session()->flash('success', trans('human_resources::app.incidents.messages.updated'));

        return redirect()->route('admin.human_resources.incidents.show', $id);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->repository->delete($id);

        return response()->json(['message' => trans('human_resources::app.incidents.messages.deleted')]);
    }

    private function nextCode(): string
    {
        return 'OCO-'.now()->format('Ym').'-'.Str::upper(Str::random(6));
    }

    private function formData(): array
    {
        return ['employees' => $this->employeeRepository->orderBy('full_name')->all(), 'contracts' => $this->contractRepository->orderBy('contract_number')->all(), 'users' => $this->userRepository->orderBy('name')->all()];
    }
}
