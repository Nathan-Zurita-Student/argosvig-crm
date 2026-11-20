<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\Http\Requests\ChecklistRequest;
use Webkul\HumanResources\Models\Checklist;
use Webkul\HumanResources\Models\ChecklistProxy;
use Webkul\HumanResources\Models\EmployeeProxy;
use Webkul\HumanResources\Models\WorkSiteProxy;
use Webkul\HumanResources\Services\ChecklistBoard;
use Webkul\User\Models\UserProxy;

class ChecklistController extends Controller
{
    public function __construct(private ChecklistBoard $board) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        // A card opened from a search result is shown even when it was completed long ago.
        if ($request->integer('card')) {
            $filters['all_done'] = true;
        }

        return view('human_resources::checklists.index', [
            'board' => $this->board->board($filters),
            'filters' => $filters,
            'filtered' => $this->board->hasFilters($filters),
            'employees' => EmployeeProxy::query()->orderBy('full_name')->get(['id', 'full_name', 'employee_number']),
            'workSites' => WorkSiteProxy::query()->orderBy('name')->get(['id', 'name', 'code']),
            'users' => UserProxy::query()->where('status', 1)->orderBy('name')->get(['id', 'name']),
            'draft' => $this->draft(),
            'can' => [
                'create' => bouncer()->hasPermission('checklists.create'),
                'edit' => bouncer()->hasPermission('checklists.edit'),
                'delete' => bouncer()->hasPermission('checklists.delete'),
            ],
        ]);
    }

    public function store(ChecklistRequest $request): JsonResponse
    {
        $card = $this->board->create($request->validated());

        return response()->json([
            'message' => trans('human_resources::checklists.messages.created'),
            'card' => $this->board->present($card),
        ], 201);
    }

    public function update(ChecklistRequest $request, int $id): JsonResponse
    {
        $card = $this->board->update($this->card($id), $request->validated());

        return response()->json([
            'message' => trans('human_resources::checklists.messages.updated'),
            'card' => $this->board->present($card->refresh()),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->card($id)->delete();

        return response()->json(['message' => trans('human_resources::checklists.messages.deleted')]);
    }

    public function move(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cards' => ['required', 'array', 'max:500'],
            'cards.*.id' => ['required', 'integer', 'distinct', 'exists:hr_checklists,id'],
            'cards.*.column_id' => ['required', 'integer', 'exists:hr_checklist_columns,id'],
            'cards.*.position' => ['required', 'integer', 'min:0'],
        ]);

        $cards = $this->board->move($data['cards']);

        return response()->json([
            'message' => trans('human_resources::checklists.messages.moved'),
            'cards' => $cards->map(fn (Checklist $card) => $this->board->present($card->refresh()))->all(),
        ]);
    }

    public function storeItem(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $card = $this->card($id);
        $this->board->addItem($card, $data['title']);

        return response()->json(['card' => $this->board->present($card->refresh())], 201);
    }

    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'completed' => ['sometimes', 'boolean'],
        ]);
        $card = $this->card($id);
        $this->board->updateItem($card->items()->findOrFail($itemId), $data);

        return response()->json(['card' => $this->board->present($card->refresh())]);
    }

    public function destroyItem(int $id, int $itemId): JsonResponse
    {
        $card = $this->card($id);
        $card->items()->findOrFail($itemId)->delete();

        return response()->json(['card' => $this->board->present($card->refresh())]);
    }

    /**
     * A card prepared by the AI assistant, opened in the "new card" modal for the person to
     * review and create. Nothing is saved until they do.
     */
    private function draft(): ?array
    {
        if (! old('checklist_draft') || ! bouncer()->hasPermission('checklists.create')) {
            return null;
        }

        $id = fn (string $field) => is_numeric(old($field)) ? (int) old($field) : '';

        return [
            'column_id' => $id('column_id') ?: null,
            'title' => (string) old('title', ''),
            'description' => (string) old('description', ''),
            'employee_id' => $id('employee_id'),
            'work_site_id' => $id('work_site_id'),
            'assigned_to' => $id('assigned_to'),
            'due_date' => (string) old('due_date', ''),
            'items' => array_values(array_filter((array) old('items', []), 'is_string')),
        ];
    }

    private function card(int $id): Checklist
    {
        return ChecklistProxy::query()->findOrFail($id);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'employee_id' => ['nullable', 'integer'],
            'work_site_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'all_done' => ['nullable', 'boolean'],
        ]);
    }
}
