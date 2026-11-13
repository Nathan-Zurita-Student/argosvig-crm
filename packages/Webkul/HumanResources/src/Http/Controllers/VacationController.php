<?php

namespace Webkul\HumanResources\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\DataGrids\VacationDataGrid;
use Webkul\HumanResources\Http\Requests\VacationRequest;
use Webkul\HumanResources\Models\EmployeeProxy;
use Webkul\HumanResources\Repositories\VacationRepository;

class VacationController extends Controller
{
    public function __construct(protected VacationRepository $vacationRepository) {}

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(VacationDataGrid::class)->process();
        }

        return view('human_resources::vacations.index');
    }

    public function create(): View
    {
        return view('human_resources::vacations.create', ['employees' => $this->employees()]);
    }

    public function store(VacationRequest $request)
    {
        Event::dispatch('human_resources.vacation.create.before');

        $vacation = $this->vacationRepository->create($this->payload($request));

        Event::dispatch('human_resources.vacation.create.after', $vacation);

        session()->flash('success', trans('human_resources::app.vacations.messages.created'));

        return redirect()->route('admin.human_resources.vacations.index');
    }

    public function edit(int $id): View
    {
        return view('human_resources::vacations.edit', [
            'vacation' => $this->vacationRepository->findOrFail($id),
            'employees' => $this->employees(),
        ]);
    }

    public function update(VacationRequest $request, int $id)
    {
        Event::dispatch('human_resources.vacation.update.before', $id);

        $vacation = $this->vacationRepository->update($this->payload($request), $id);

        Event::dispatch('human_resources.vacation.update.after', $vacation);

        session()->flash('success', trans('human_resources::app.vacations.messages.updated'));

        return redirect()->route('admin.human_resources.vacations.index');
    }

    public function destroy(int $id): JsonResponse
    {
        Event::dispatch('human_resources.vacation.delete.before', $id);
        $this->vacationRepository->delete($id);
        Event::dispatch('human_resources.vacation.delete.after', $id);

        return response()->json(['message' => trans('human_resources::app.vacations.messages.deleted')]);
    }

    private function employees()
    {
        return EmployeeProxy::query()->orderBy('full_name')->get();
    }

    private function payload(VacationRequest $request): array
    {
        $data = $request->validated();
        $data['days'] = Carbon::parse($data['starts_at'])->diffInDays(Carbon::parse($data['ends_at'])) + 1;

        return $data;
    }
}
