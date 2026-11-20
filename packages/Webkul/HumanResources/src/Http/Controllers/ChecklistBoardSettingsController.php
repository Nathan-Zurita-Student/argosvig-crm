<?php

namespace Webkul\HumanResources\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\HumanResources\Models\ChecklistColumn;
use Webkul\HumanResources\Services\ChecklistBoard;

class ChecklistBoardSettingsController extends Controller
{
    public function __construct(private ChecklistBoard $board) {}

    public function edit(): View
    {
        return view('human_resources::checklists.settings', [
            'columns' => $this->board->columns()->map(fn (ChecklistColumn $column) => [
                ...$this->board->presentColumn($column),
                'cards' => $column->checklists()->count(),
            ])->values(),
            'colors' => ChecklistColumn::COLORS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'columns' => ['required', 'array', 'min:1', 'max:20'],
            'columns.*.id' => ['nullable', 'integer'],
            'columns.*.label' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'columns.*.color' => ['required', Rule::in(ChecklistColumn::COLORS)],
            'columns.*.completes' => ['nullable', 'boolean'],
        ], [], [
            'columns.*.label' => trans('human_resources::checklists.settings.name'),
            'columns.*.color' => trans('human_resources::checklists.settings.color'),
        ]);

        $this->board->saveColumns(array_values($data['columns']));

        session()->flash('success', trans('human_resources::checklists.settings.saved'));

        return redirect()->route('admin.settings.checklist_board.edit');
    }
}
