<?php

namespace Webkul\HumanResources\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webkul\HumanResources\Models\Checklist;
use Webkul\HumanResources\Models\ChecklistColumn;
use Webkul\HumanResources\Models\ChecklistColumnProxy;
use Webkul\HumanResources\Models\ChecklistItem;
use Webkul\HumanResources\Models\ChecklistProxy;

/**
 * The checklist board: configurable columns, cards that move between them and the
 * items checked on each card. A card in the "done" column is completed, and who
 * checked each item stays on record so finished checklists can be found later.
 */
class ChecklistBoard
{
    /**
     * Without a search, the done column shows only what was completed recently.
     */
    public const RECENT_DONE_DAYS = 30;

    public function columns(): Collection
    {
        return ChecklistColumnProxy::query()->orderBy('position')->orderBy('id')->get();
    }

    /**
     * The columns with their cards, narrowed by the board filters.
     */
    public function board(array $filters): array
    {
        $columns = $this->columns();
        $filtered = $this->hasFilters($filters);

        $query = ChecklistProxy::query()
            ->with(['column', 'items.completer', 'employee', 'workSite', 'assignee', 'completer'])
            ->orderBy('position')
            ->orderBy('id');

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';

            $query->where(fn ($q) => $q->where('title', 'like', $search)
                ->orWhere('description', 'like', $search)
                ->orWhereHas('items', fn ($item) => $item->where('title', 'like', $search))
                ->orWhereHas('employee', fn ($employee) => $employee->withTrashed()->where('full_name', 'like', $search))
                ->orWhereHas('workSite', fn ($site) => $site->withTrashed()->where('name', 'like', $search)));
        }

        foreach (['employee_id', 'work_site_id', 'assigned_to'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        $completing = $columns->where('completes', true)->pluck('id')->all();

        if ($completing && ! $filtered && empty($filters['all_done'])) {
            $query->where(fn ($q) => $q->whereNotIn('column_id', $completing)
                ->orWhere('completed_at', '>=', now()->subDays(self::RECENT_DONE_DAYS)));
        }

        $cards = $query->get()->groupBy('column_id');

        return $columns->map(fn (ChecklistColumn $column) => [
            ...$this->presentColumn($column),
            'cards' => ($cards[$column->id] ?? collect())->map(fn (Checklist $card) => $this->present($card))->values()->all(),
        ])->all();
    }

    public function hasFilters(array $filters): bool
    {
        return collect($filters)->only(['search', 'employee_id', 'work_site_id', 'assigned_to'])->filter()->isNotEmpty();
    }

    public function create(array $data): Checklist
    {
        return DB::transaction(function () use ($data) {
            $column = ChecklistColumnProxy::query()->findOrFail($data['column_id']);

            $card = ChecklistProxy::query()->create([
                ...collect($data)->only(['title', 'description', 'employee_id', 'work_site_id', 'assigned_to', 'due_date'])->all(),
                'column_id' => $column->id,
                'position' => (int) ChecklistProxy::query()->where('column_id', $column->id)->max('position') + 1,
                'created_by' => auth()->guard('user')->id(),
                ...$this->completion($column, null),
            ]);

            foreach (array_values(array_filter($data['items'] ?? [], fn ($title) => trim((string) $title) !== '')) as $position => $title) {
                $card->items()->create(['title' => trim($title), 'position' => $position]);
            }

            return $card;
        });
    }

    public function update(Checklist $card, array $data): Checklist
    {
        $card->update(collect($data)->only(['title', 'description', 'employee_id', 'work_site_id', 'assigned_to', 'due_date'])->all());

        return $card;
    }

    /**
     * Saves the order of the cards after a drag, as AcadFlow does: every card sent
     * gets its column and position. A card entering a column that completes cards is
     * completed; one leaving for a column that does not is reopened.
     *
     * @param  array<int, array{id: int, column_id: int, position: int}>  $moves
     */
    public function move(array $moves): Collection
    {
        return DB::transaction(function () use ($moves) {
            $columns = $this->columns()->keyBy('id');
            $cards = ChecklistProxy::query()->whereIn('id', array_column($moves, 'id'))->lockForUpdate()->get()->keyBy('id');

            foreach ($moves as $move) {
                $card = $cards[$move['id']];
                $column = $columns[$move['column_id']];

                $card->update([
                    'column_id' => $column->id,
                    'position' => $move['position'],
                    ...$this->completion($column, $card),
                ]);
            }

            return $cards->values();
        });
    }

    public function addItem(Checklist $card, string $title): ChecklistItem
    {
        return $card->items()->create([
            'title' => trim($title),
            'position' => (int) $card->items()->max('position') + 1,
        ]);
    }

    public function updateItem(ChecklistItem $item, array $data): ChecklistItem
    {
        $attributes = collect($data)->only('title')->all();

        if (array_key_exists('completed', $data)) {
            $attributes += $data['completed']
                ? ['completed_at' => $item->completed_at ?? now(), 'completed_by' => $item->completed_by ?? auth()->guard('user')->id()]
                : ['completed_at' => null, 'completed_by' => null];
        }

        $item->update($attributes);

        return $item;
    }

    /**
     * Saves the columns edited in the settings, in the order received. A row without an
     * id is a new column. Any column may be removed as long as one remains; the cards
     * of a removed column move to the first column that does not complete cards, or to
     * the first column when every one does. Cards follow a change of "completes".
     *
     * @param  array<int, array{id?: int|null, label: string, color: string, completes?: bool}>  $rows
     */
    public function saveColumns(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            $existing = ChecklistColumnProxy::query()->lockForUpdate()->get()->keyBy('id');
            $keptIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id);

            if ($keptIds->diff($existing->keys())->isNotEmpty()) {
                throw ValidationException::withMessages(['columns' => trans('human_resources::checklists.settings.unknown_column')]);
            }

            $ordered = collect($rows)->values()->map(function (array $row, int $position) use ($existing) {
                $attributes = [
                    'label' => trim($row['label']),
                    'color' => $row['color'],
                    'completes' => (bool) ($row['completes'] ?? false),
                    'position' => $position,
                ];

                if (empty($row['id'])) {
                    return ChecklistColumnProxy::query()->create([...$attributes, 'key' => $this->uniqueKey($row['label'])]);
                }

                $column = $existing[(int) $row['id']];
                $changed = $column->completes !== $attributes['completes'];
                $column->update($attributes);

                if ($changed) {
                    $column->checklists()->get()->each(fn (Checklist $card) => $card->update($this->completion($column, $card)));
                }

                return $column;
            });

            // Plain ids: an Eloquent collection re-indexes on except(), so its keys are not ids.
            $removedIds = $existing->keys()->map(fn ($id) => (int) $id)->diff($keptIds)->values()->all();

            if (! $removedIds) {
                return;
            }

            $fallback = $ordered->first(fn (ChecklistColumn $column) => ! $column->completes) ?? $ordered->first();
            $position = (int) ChecklistProxy::query()->where('column_id', $fallback->id)->max('position');

            ChecklistProxy::query()
                ->whereIn('column_id', $removedIds)
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->each(fn (Checklist $card) => $card->update([
                    'column_id' => $fallback->id,
                    'position' => ++$position,
                    ...$this->completion($fallback, $card),
                ]));

            ChecklistColumnProxy::query()->whereIn('id', $removedIds)->delete();
        });
    }

    public function presentColumn(ChecklistColumn $column): array
    {
        return [
            'id' => $column->id,
            'key' => $column->key,
            'label' => $column->label,
            'color' => $column->color,
            'completes' => $column->completes,
        ];
    }

    public function present(Checklist $card): array
    {
        $card->loadMissing(['column', 'items.completer', 'employee', 'workSite', 'assignee', 'completer']);
        $items = $card->items;

        return [
            'id' => $card->id,
            'column_id' => $card->column_id,
            'position' => $card->position,
            'title' => $card->title,
            'description' => $card->description,
            'employee_id' => $card->employee_id,
            'employee' => $card->employee?->full_name,
            'work_site_id' => $card->work_site_id,
            'work_site' => $card->workSite?->name,
            'assigned_to' => $card->assigned_to,
            'assignee' => $card->assignee?->name,
            'due_date' => $card->due_date?->toDateString(),
            'due_label' => $card->due_date?->format('d/m/Y'),
            'overdue' => $card->due_date !== null && $card->completed_at === null && $card->due_date->isBefore(today()),
            'completed_label' => $card->completed_at?->setTimezone(config('app.timezone'))->format('d/m/Y H:i'),
            'completed_by' => $card->completer?->name,
            'items_done' => $items->whereNotNull('completed_at')->count(),
            'items_total' => $items->count(),
            'items' => $items->map(fn (ChecklistItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'completed' => $item->completed_at !== null,
                'completed_label' => $item->completed_at?->setTimezone(config('app.timezone'))->format('d/m/Y H:i'),
                'completed_by' => $item->completer?->name,
            ])->values()->all(),
        ];
    }

    /**
     * The completion a card gets in a column. A card already completed keeps its date and
     * author when it moves between columns that complete cards.
     */
    private function completion(ChecklistColumn $column, ?Checklist $card): array
    {
        if (! $column->completes) {
            return ['completed_at' => null, 'completed_by' => null];
        }

        return $card?->completed_at
            ? ['completed_at' => $card->completed_at, 'completed_by' => $card->completed_by]
            : ['completed_at' => now(), 'completed_by' => auth()->guard('user')->id()];
    }

    private function uniqueKey(string $label): string
    {
        $base = Str::limit(Str::slug($label, '_'), 50, '') ?: 'coluna';
        $key = $base;
        $suffix = 1;

        while (ChecklistColumnProxy::query()->where('key', $key)->exists()) {
            $key = $base.'_'.(++$suffix);
        }

        return $key;
    }
}
