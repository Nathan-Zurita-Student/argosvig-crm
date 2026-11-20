<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\HumanResources\Models\Checklist;
use Webkul\HumanResources\Models\ChecklistColumn;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

function checklistUser(array $permissions): User
{
    $role = Role::query()->create([
        'name' => fake()->uuid(),
        'description' => 'Checklist test',
        'permission_type' => 'custom',
        'permissions' => $permissions,
    ]);

    return User::query()->create([
        'name' => 'Checklist test',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('test-secret'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $role->id,
    ]);
}

/**
 * The board as installed, whatever was configured on this database. The tests run in a
 * transaction, so the cards and columns replaced here come back afterwards.
 */
function checklistDefaultBoard(): void
{
    Checklist::query()->delete();
    ChecklistColumn::query()->delete();

    foreach (ChecklistColumn::DEFAULTS as $position => $column) {
        ChecklistColumn::query()->create([
            ...$column,
            'label' => trans('human_resources::checklists.defaults.'.$column['key']),
            'position' => $position,
        ]);
    }
}

uses()->beforeEach(fn () => checklistDefaultBoard());

function checklistColumn(string $key): ChecklistColumn
{
    return ChecklistColumn::query()->where('key', $key)->sole();
}

/**
 * The settings form as the page posts it: every column in order, with its id.
 */
function checklistColumnsPayload(?callable $change = null): array
{
    $rows = ChecklistColumn::query()->orderBy('position')->get()
        ->map(fn (ChecklistColumn $column) => ['id' => $column->id, 'label' => $column->label, 'color' => $column->color, 'completes' => $column->completes ? '1' : '0'])
        ->all();

    return ['columns' => $change ? $change($rows) : $rows];
}

it('starts with three columns, the last one completing cards, and shows the board and its settings', function () {
    $this->actingAs(getDefaultAdmin(), 'user');

    expect(ChecklistColumn::query()->orderBy('position')->pluck('completes', 'key')->all())
        ->toBe(['todo' => false, 'doing' => false, 'done' => true]);

    $this->get(route('admin.human_resources.checklists.index'))
        ->assertOk()
        ->assertSee(trans('human_resources::checklists.title'))
        ->assertSee(checklistColumn('todo')->label);

    $this->get(route('admin.settings.checklist_board.edit'))
        ->assertOk()
        ->assertSee(trans('human_resources::checklists.settings.title'));

    $this->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee(trans('human_resources::checklists.settings.info'));
});

it('creates a card with items and completes it when it enters the done column', function () {
    $admin = getDefaultAdmin();
    $this->actingAs($admin, 'user');
    $todo = checklistColumn('todo');
    $done = checklistColumn('done');

    $card = $this->postJson(route('admin.human_resources.checklists.store'), [
        'column_id' => $todo->id,
        'title' => 'Ronda do posto central',
        'items' => ['Conferir extintores', '  ', 'Testar rádio'],
    ])->assertCreated()->json('card');

    expect($card['items_total'])->toBe(2)
        ->and($card['completed_label'])->toBeNull();

    $item = $card['items'][0];

    $this->putJson(route('admin.human_resources.checklists.items.update', ['id' => $card['id'], 'itemId' => $item['id']]), ['completed' => true])
        ->assertOk()
        ->assertJsonPath('card.items_done', 1)
        ->assertJsonPath('card.items.0.completed_by', $admin->name);

    $this->assertDatabaseHas('hr_checklist_items', ['id' => $item['id'], 'completed_by' => $admin->id]);

    $this->postJson(route('admin.human_resources.checklists.move'), [
        'cards' => [['id' => $card['id'], 'column_id' => $done->id, 'position' => 0]],
    ])->assertOk()->assertJsonPath('cards.0.completed_by', $admin->name);

    $stored = Checklist::query()->findOrFail($card['id']);
    expect($stored->column_id)->toBe($done->id)
        ->and($stored->completed_at)->not->toBeNull()
        ->and($stored->completed_by)->toBe($admin->id);

    $this->postJson(route('admin.human_resources.checklists.move'), [
        'cards' => [['id' => $card['id'], 'column_id' => $todo->id, 'position' => 0]],
    ])->assertOk();

    expect($stored->refresh()->completed_at)->toBeNull();

    $this->putJson(route('admin.human_resources.checklists.items.update', ['id' => $card['id'], 'itemId' => $item['id']]), ['completed' => false])
        ->assertJsonPath('card.items_done', 0);

    $this->deleteJson(route('admin.human_resources.checklists.delete', $card['id']))->assertOk();
    $this->assertDatabaseMissing('hr_checklist_items', ['checklist_id' => $card['id']]);
});

it('hides old completed cards until the board is searched', function () {
    $this->actingAs(getDefaultAdmin(), 'user');

    Checklist::query()->create([
        'column_id' => checklistColumn('done')->id,
        'title' => 'Checklist-antigo-de-agosto',
        'completed_at' => now()->subDays(90),
    ]);

    $url = route('admin.human_resources.checklists.index');

    $this->get($url)->assertOk()->assertDontSee('Checklist-antigo-de-agosto');
    $this->get($url.'?search=antigo-de-agosto')->assertOk()->assertSee('Checklist-antigo-de-agosto');
    $this->get($url.'?all_done=1')->assertOk()->assertSee('Checklist-antigo-de-agosto');
});

it('adds, renames, reorders and removes columns in the settings', function () {
    $this->actingAs(getDefaultAdmin(), 'user');
    $update = route('admin.settings.checklist_board.update');

    $this->put($update, checklistColumnsPayload(function (array $rows) {
        $rows[0]['label'] = 'Pendentes';
        $rows[] = ['id' => null, 'label' => 'Aguardando cliente', 'color' => 'orange'];

        return [$rows[0], $rows[3], $rows[1], $rows[2]];
    }))->assertRedirect(route('admin.settings.checklist_board.edit'));

    $added = ChecklistColumn::query()->where('label', 'Aguardando cliente')->sole();

    expect($added->key)->toBe('aguardando_cliente')
        ->and($added->completes)->toBeFalse()
        ->and(ChecklistColumn::query()->orderBy('position')->pluck('key')->all())->toBe(['todo', 'aguardando_cliente', 'doing', 'done'])
        ->and(checklistColumn('todo')->label)->toBe('Pendentes');

    $card = Checklist::query()->create(['column_id' => $added->id, 'title' => 'Esperando retorno']);
    Checklist::query()->create(['column_id' => checklistColumn('todo')->id, 'title' => 'Já estava', 'position' => 4]);

    // Removing the column moves its cards to the first column.
    $this->put($update, checklistColumnsPayload(fn (array $rows) => array_values(array_filter($rows, fn ($row) => $row['id'] !== $added->id))))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseMissing('hr_checklist_columns', ['id' => $added->id]);
    expect($card->refresh()->column_id)->toBe(checklistColumn('todo')->id)
        ->and($card->position)->toBe(5);
});

it('saves the columns in the order of the fields keyed by column, as the settings page posts them', function () {
    $this->actingAs(getDefaultAdmin(), 'user');
    $todo = checklistColumn('todo');
    $doing = checklistColumn('doing');
    $done = checklistColumn('done');

    // The done column dragged to the front: each field keeps its own label.
    $this->put(route('admin.settings.checklist_board.update'), ['columns' => [
        'column_'.$done->id => ['id' => $done->id, 'label' => $done->label, 'color' => 'green', 'completes' => '1'],
        'new_0' => ['id' => '', 'label' => 'Revisão', 'color' => 'purple', 'completes' => '0'],
        'column_'.$todo->id => ['id' => $todo->id, 'label' => $todo->label, 'color' => 'gray', 'completes' => '0'],
        'column_'.$doing->id => ['id' => $doing->id, 'label' => $doing->label, 'color' => 'blue', 'completes' => '0'],
    ]])->assertSessionHasNoErrors();

    expect(ChecklistColumn::query()->orderBy('position')->pluck('label', 'key')->all())->toBe([
        'done' => $done->label,
        'revisao' => 'Revisão',
        'todo' => $todo->label,
        'doing' => $doing->label,
    ]);
});

it('removes any column, the completing one included, while one remains', function () {
    $this->actingAs(getDefaultAdmin(), 'user');
    $update = route('admin.settings.checklist_board.update');
    $done = checklistColumn('done');
    $finished = Checklist::query()->create([
        'column_id' => $done->id, 'title' => 'Encerrado', 'completed_at' => now(), 'completed_by' => getDefaultAdmin()->id,
    ]);

    // The completed card goes to the first column that does not complete cards, and reopens.
    $this->put($update, checklistColumnsPayload(fn (array $rows) => array_values(array_filter($rows, fn ($row) => $row['id'] !== $done->id))))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('hr_checklist_columns', ['id' => $done->id]);
    expect($finished->refresh()->column_id)->toBe(checklistColumn('todo')->id)
        ->and($finished->completed_at)->toBeNull();

    // Only one column left: still allowed.
    $todo = checklistColumn('todo');
    $this->put($update, checklistColumnsPayload(fn (array $rows) => array_values(array_filter($rows, fn ($row) => $row['id'] === $todo->id))))
        ->assertSessionHasNoErrors();

    expect(ChecklistColumn::query()->pluck('key')->all())->toBe(['todo']);

    // No column at all: refused.
    $this->put($update, ['columns' => []])->assertSessionHasErrors('columns');
    expect(ChecklistColumn::query()->count())->toBe(1);
});

it('completes or reopens the cards of a column when its option changes', function () {
    $admin = getDefaultAdmin();
    $this->actingAs($admin, 'user');
    $doing = checklistColumn('doing');
    $done = checklistColumn('done');
    $card = Checklist::query()->create(['column_id' => $doing->id, 'title' => 'Em curso']);

    $toggle = fn (bool $on) => checklistColumnsPayload(function (array $rows) use ($doing, $on) {
        return array_map(fn ($row) => $row['id'] === $doing->id ? [...$row, 'completes' => $on ? '1' : '0'] : $row, $rows);
    });

    $this->put(route('admin.settings.checklist_board.update'), $toggle(true))->assertSessionHasNoErrors();
    expect($card->refresh()->completed_at)->not->toBeNull()
        ->and($card->completed_by)->toBe($admin->id);

    // Moving between two completing columns keeps the original completion.
    $completedAt = $card->completed_at->toDateTimeString();
    $this->travel(2)->hours();
    $this->postJson(route('admin.human_resources.checklists.move'), ['cards' => [['id' => $card->id, 'column_id' => $done->id, 'position' => 0]]])->assertOk();
    expect($card->refresh()->completed_at->toDateTimeString())->toBe($completedAt);

    $this->postJson(route('admin.human_resources.checklists.move'), ['cards' => [['id' => $card->id, 'column_id' => $doing->id, 'position' => 0]]])->assertOk();
    $this->put(route('admin.settings.checklist_board.update'), $toggle(false))->assertSessionHasNoErrors();
    expect($card->refresh()->completed_at)->toBeNull();
});

it('refuses duplicate column names', function () {
    $this->actingAs(getDefaultAdmin(), 'user');

    $this->put(route('admin.settings.checklist_board.update'), checklistColumnsPayload(function (array $rows) {
        $rows[1]['label'] = mb_strtoupper($rows[0]['label']);

        return $rows;
    }))->assertSessionHasErrors('columns.1.label');

    expect(ChecklistColumn::query()->count())->toBe(3);
});

it('enforces the board and settings permissions on direct requests', function () {
    $todo = checklistColumn('todo');
    $card = Checklist::query()->create(['column_id' => $todo->id, 'title' => 'Protegido']);

    $this->actingAs(checklistUser(['operations', 'checklists']), 'user');
    $this->get(route('admin.human_resources.checklists.index'))->assertOk();
    $this->postJson(route('admin.human_resources.checklists.store'), ['column_id' => $todo->id, 'title' => 'Sem permissão'])->assertUnauthorized();
    $this->postJson(route('admin.human_resources.checklists.move'), ['cards' => [['id' => $card->id, 'column_id' => checklistColumn('done')->id, 'position' => 0]]])->assertUnauthorized();
    $this->postJson(route('admin.human_resources.checklists.items.store', $card->id), ['title' => 'Item'])->assertUnauthorized();
    $this->deleteJson(route('admin.human_resources.checklists.delete', $card->id))->assertUnauthorized();
    $this->get(route('admin.settings.checklist_board.edit'))->assertUnauthorized();
    $this->put(route('admin.settings.checklist_board.update'), checklistColumnsPayload())->assertUnauthorized();

    $this->actingAs(checklistUser(['human-resources', 'human-resources.employees']), 'user');
    $this->get(route('admin.human_resources.checklists.index'))->assertUnauthorized();

    $this->actingAs(checklistUser(['settings', 'settings.operations', 'settings.operations.checklist_board']), 'user');
    $this->get(route('admin.settings.checklist_board.edit'))->assertOk();
    $this->get(route('admin.human_resources.checklists.index'))->assertUnauthorized();

    expect($card->refresh()->column_id)->toBe($todo->id);
});
