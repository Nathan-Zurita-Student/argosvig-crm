<x-admin::layouts>
    <x-slot:title>
        @lang('human_resources::checklists.title')
    </x-slot>

    @php
        $input = 'w-full rounded border border-gray-300 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400';
        $label = 'mb-1.5 flex items-center gap-1 text-sm font-normal text-gray-800 dark:text-white';
    @endphp

    <div class="flex flex-col gap-4">
        <!-- Header and filters. Rendered by Blade, kept out of Vue's template compiler. -->
        <div
            v-pre
            class="flex flex-col rounded-lg border border-gray-300 bg-white text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
        >
            <!-- The columns are configured in Administração > Operação, not from the board. -->
            <div class="flex flex-col gap-1 px-4 py-2">
                <div class="text-xl font-bold dark:text-white">
                    @lang('human_resources::checklists.title')
                </div>

                <p class="text-gray-600 dark:text-gray-300">
                    @lang('human_resources::checklists.hint')
                </p>
            </div>

            <form
                method="GET"
                class="flex flex-wrap items-end gap-3 border-t border-gray-300 px-4 py-3 dark:border-gray-800"
            >
                <label class="min-w-[220px] flex-1">
                    <span class="{{ $label }}">@lang('human_resources::checklists.filters.search')</span>

                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="@lang('human_resources::checklists.filters.search_placeholder')"
                        class="{{ $input }}"
                    >
                </label>

                <label class="min-w-[180px]">
                    <span class="{{ $label }}">@lang('human_resources::checklists.fields.employee')</span>

                    <select data-searchable name="employee_id" class="{{ $input }}">
                        <option value="">@lang('human_resources::checklists.filters.all')</option>

                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) ($filters['employee_id'] ?? '') === (string) $employee->id)>{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="min-w-[180px]">
                    <span class="{{ $label }}">@lang('human_resources::checklists.fields.work_site')</span>

                    <select data-searchable name="work_site_id" class="{{ $input }}">
                        <option value="">@lang('human_resources::checklists.filters.all')</option>

                        @foreach ($workSites as $site)
                            <option value="{{ $site->id }}" @selected((string) ($filters['work_site_id'] ?? '') === (string) $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="min-w-[180px]">
                    <span class="{{ $label }}">@lang('human_resources::checklists.fields.assignee')</span>

                    <select data-searchable name="assigned_to" class="{{ $input }}">
                        <option value="">@lang('human_resources::checklists.filters.all')</option>

                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="submit" class="primary-button">@lang('human_resources::checklists.filters.apply')</button>

                @if ($filtered || ! empty($filters['all_done']))
                    <a href="{{ route('admin.human_resources.checklists.index') }}" class="transparent-button">@lang('human_resources::checklists.filters.clear')</a>
                @endif
            </form>

            @unless ($filtered || ! empty($filters['all_done']) || ! collect($board)->contains('completes', true))
                <p class="border-t border-gray-300 px-4 py-2 text-xs text-gray-600 dark:border-gray-800 dark:text-gray-300">
                    @lang('human_resources::checklists.recent_done', ['days' => \Webkul\HumanResources\Services\ChecklistBoard::RECENT_DONE_DAYS])

                    <a href="{{ route('admin.human_resources.checklists.index', ['all_done' => 1]) }}" class="text-brandColor hover:underline">@lang('human_resources::checklists.show_all_done')</a>
                </p>
            @endunless
        </div>

        <v-checklist-board>
            <x-admin::shimmer.leads.index.kanban />
        </v-checklist-board>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-checklist-board-template"
        >
            <!-- The board takes the height left on the screen, so the page itself does not scroll. -->
            <div
                ref="board"
                class="flex gap-2.5 overflow-x-auto"
                :style="{ height: boardHeight + 'px' }"
            >
                <!-- Columns -->
                <div
                    class="flex h-full min-w-[275px] max-w-[275px] flex-col gap-1 rounded-lg border border-gray-300 bg-white dark:border-gray-800 dark:bg-gray-900"
                    v-for="column in columns"
                    :key="column.id"
                >
                    <!-- Column header -->
                    <div class="flex items-center justify-between px-2 py-3">
                        <span class="flex items-center gap-2 text-xs font-medium dark:text-white">
                            <span
                                class="h-2 w-2 rounded-full"
                                :class="dotColor(column.color)"
                            ></span>

                            @{{ column.label }} (@{{ column.cards.length }})
                        </span>

                        <span
                            v-if="can.create"
                            class="icon-add cursor-pointer rounded p-1 text-lg text-gray-600 transition-all hover:bg-gray-200 hover:text-gray-800 dark:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-white"
                            title="@lang('human_resources::checklists.create.title')"
                            @click="openCreate(column)"
                        ></span>
                    </div>

                    <!-- Cards -->
                    <div class="relative min-h-0 flex-1">
                        <!-- Laid over the list, not inside it, so a dragged card lands at the top of an empty column. -->
                        <p
                            class="pointer-events-none absolute inset-x-0 top-0 py-6 text-center text-xs text-gray-400"
                            v-if="! column.cards.length && ! dragging"
                        >
                            @lang('human_resources::checklists.empty_column')
                        </p>

                        <draggable
                            class="flex h-full flex-col gap-2 overflow-y-auto p-2"
                            ghost-class="draggable-ghost"
                            v-bind="{ animation: 200 }"
                            :list="column.cards"
                            :disabled="! can.edit"
                            item-key="id"
                            group="checklists"
                            @start="dragging = true"
                            @end="dragging = false"
                            @change="handleChange(column, $event)"
                        >
                            <template #item="{ element }">
                                <div
                                    class="flex cursor-pointer flex-col gap-3 rounded-lg border border-gray-300 bg-gray-100 p-2 shadow-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300"
                                    @click="openCard(element)"
                                >
                                    <p class="text-xs font-medium dark:text-white">
                                        @{{ element.title }}
                                    </p>

                                    <!-- Item progress -->
                                    <div
                                        class="flex items-center gap-2"
                                        v-if="element.items_total"
                                    >
                                        <div class="h-1 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                                            <div
                                                class="h-1 bg-green-500"
                                                :style="{ width: (element.items_done / element.items_total) * 100 + '%' }"
                                            ></div>
                                        </div>

                                        <span class="text-[10px] font-medium">
                                            @{{ element.items_done }}/@{{ element.items_total }}
                                        </span>
                                    </div>

                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            class="flex items-center gap-1 rounded-xl bg-gray-200 px-2 py-1 text-xs font-medium dark:bg-gray-800 dark:text-white"
                                            v-if="element.employee"
                                        >
                                            <span class="icon-user text-sm"></span>

                                            @{{ element.employee }}
                                        </span>

                                        <span
                                            class="flex items-center gap-1 rounded-xl bg-gray-200 px-2 py-1 text-xs font-medium dark:bg-gray-800 dark:text-white"
                                            v-if="element.work_site"
                                        >
                                            <span class="icon-location text-sm"></span>

                                            @{{ element.work_site }}
                                        </span>

                                        <span
                                            class="flex items-center gap-1 rounded-xl bg-gray-200 px-2 py-1 text-xs font-medium dark:bg-gray-800 dark:text-white"
                                            v-if="element.assignee"
                                        >
                                            <span class="icon-settings-user text-sm"></span>

                                            @{{ element.assignee }}
                                        </span>

                                        <span
                                            class="flex items-center gap-1 rounded-xl px-2 py-1 text-xs font-medium"
                                            :class="element.overdue ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-gray-200 dark:bg-gray-800 dark:text-white'"
                                            v-if="element.due_label"
                                        >
                                            <span class="icon-calendar text-sm"></span>

                                            @{{ element.due_label }}
                                        </span>
                                    </div>

                                    <p
                                        class="text-[10px] text-gray-500 dark:text-gray-400"
                                        v-if="element.completed_label"
                                    >
                                        @{{ completedText(element) }}
                                    </p>
                                </div>
                            </template>
                        </draggable>
                    </div>
                </div>

                <!-- Create card -->
                <form @submit.prevent="create">
                    <x-admin::modal ref="createModal">
                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                @lang('human_resources::checklists.create.title')
                            </h3>
                        </x-slot>

                        <x-slot:content>
                            <div class="mb-4">
                                <label class="{{ $label }} required" for="checklist-title">@lang('human_resources::checklists.fields.title')</label>

                                <input
                                    id="checklist-title"
                                    type="text"
                                    maxlength="150"
                                    required
                                    v-model="form.title"
                                    class="{{ $input }}"
                                >
                            </div>

                            <div class="mb-4">
                                <label class="{{ $label }} required" for="checklist-column">@lang('human_resources::checklists.fields.column')</label>

                                <select id="checklist-column" v-model="form.column_id" class="{{ $input }}">
                                    <option v-for="column in columns" :key="column.id" :value="column.id">@{{ column.label }}</option>
                                </select>
                            </div>

                            @include('human_resources::checklists.fields', ['model' => 'form', 'prefix' => 'create'])

                            <div class="mb-4">
                                <label class="{{ $label }}" for="checklist-items">@lang('human_resources::checklists.fields.items')</label>

                                <textarea
                                    id="checklist-items"
                                    rows="5"
                                    v-model="form.items_text"
                                    placeholder="@lang('human_resources::checklists.create.items_placeholder')"
                                    class="{{ $input }}"
                                ></textarea>
                            </div>
                        </x-slot>

                        <x-slot:footer>
                            <button
                                type="submit"
                                class="primary-button"
                                :disabled="saving"
                            >
                                @lang('human_resources::checklists.create.save')
                            </button>
                        </x-slot>
                    </x-admin::modal>
                </form>

                <!-- Card details -->
                <x-admin::drawer
                    ref="cardDrawer"
                    width="560px"
                >
                    <x-slot:header>
                        <div class="flex flex-col gap-1">
                            <p class="text-xl font-medium dark:text-white">
                                @{{ selected ? selected.title : '' }}
                            </p>

                            <p
                                class="text-xs text-gray-500 dark:text-gray-400"
                                v-if="selected"
                            >
                                @{{ columnLabel(selected.column_id) }}
                            </p>
                        </div>
                    </x-slot>

                    <x-slot:content>
                        <div v-if="selected" class="flex flex-col gap-6">
                            <!-- Checklist items -->
                            <div>
                                <p class="mb-2 text-base font-semibold dark:text-white">
                                    @lang('human_resources::checklists.fields.items')

                                    <span
                                        class="text-sm font-normal text-gray-500"
                                        v-if="selected.items_total"
                                    >
                                        (@{{ selected.items_done }}/@{{ selected.items_total }})
                                    </span>
                                </p>

                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                    v-if="! selected.items.length"
                                >
                                    @lang('human_resources::checklists.no_items')
                                </p>

                                <div
                                    class="group flex items-start gap-2 rounded px-1 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                                    v-for="item in selected.items"
                                    :key="item.id"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 cursor-pointer accent-blue-600"
                                        :checked="item.completed"
                                        :disabled="! can.edit"
                                        @change="toggleItem(item)"
                                    >

                                    <div class="flex flex-1 flex-col">
                                        <span
                                            class="text-sm dark:text-gray-300"
                                            :class="{ 'text-gray-500 line-through': item.completed }"
                                        >
                                            @{{ item.title }}
                                        </span>

                                        <span
                                            class="text-[10px] text-gray-500 dark:text-gray-400"
                                            v-if="item.completed_label"
                                        >
                                            @{{ completedText(item) }}
                                        </span>
                                    </div>

                                    <span
                                        class="icon-delete cursor-pointer rounded p-0.5 text-lg text-gray-500 opacity-0 hover:bg-gray-200 group-hover:opacity-100 dark:hover:bg-gray-800"
                                        title="@lang('human_resources::checklists.remove_item')"
                                        v-if="can.edit"
                                        @click="removeItem(item)"
                                    ></span>
                                </div>

                                <form
                                    class="mt-2 flex gap-2"
                                    v-if="can.edit"
                                    @submit.prevent="addItem"
                                >
                                    <input
                                        type="text"
                                        maxlength="255"
                                        v-model="newItem"
                                        placeholder="@lang('human_resources::checklists.new_item')"
                                        class="{{ $input }}"
                                    >

                                    <button
                                        type="submit"
                                        class="secondary-button"
                                        :disabled="! newItem.trim()"
                                    >
                                        @lang('human_resources::checklists.add_item')
                                    </button>
                                </form>
                            </div>

                            <!-- Details -->
                            <form
                                class="border-t border-gray-300 pt-4 dark:border-gray-800"
                                @submit.prevent="saveCard"
                            >
                                <p class="mb-3 text-base font-semibold dark:text-white">
                                    @lang('human_resources::checklists.details')
                                </p>

                                <fieldset :disabled="! can.edit">
                                    <div class="mb-4">
                                        <label class="{{ $label }} required" for="edit-title">@lang('human_resources::checklists.fields.title')</label>

                                        <input
                                            id="edit-title"
                                            type="text"
                                            maxlength="150"
                                            required
                                            v-model="selected.title"
                                            class="{{ $input }}"
                                        >
                                    </div>

                                    @include('human_resources::checklists.fields', ['model' => 'selected', 'prefix' => 'edit'])
                                </fieldset>

                                <div class="flex items-center justify-between gap-2">
                                    <button
                                        type="submit"
                                        class="primary-button"
                                        v-if="can.edit"
                                        :disabled="saving"
                                    >
                                        @lang('human_resources::checklists.save')
                                    </button>

                                    <button
                                        type="button"
                                        class="transparent-button text-red-600"
                                        v-if="can.delete"
                                        @click="deleteCard"
                                    >
                                        @lang('human_resources::checklists.delete')
                                    </button>
                                </div>
                            </form>

                            <p
                                class="text-xs text-gray-500 dark:text-gray-400"
                                v-if="selected.completed_label"
                            >
                                @{{ completedText(selected) }}
                            </p>
                        </div>
                    </x-slot>
                </x-admin::drawer>
            </div>
        </script>

        <script type="module">
            app.component('v-checklist-board', {
                template: '#v-checklist-board-template',

                data() {
                    return {
                        columns: @json($board),

                        can: @json($can),

                        employees: @json($employees->map(fn ($employee) => ['id' => $employee->id, 'name' => trim($employee->full_name.' · '.$employee->employee_number, ' ·')])),

                        workSites: @json($workSites->map(fn ($site) => ['id' => $site->id, 'name' => $site->name])),

                        users: @json($users->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])),

                        dotColors: {
                            gray: 'bg-gray-400',
                            blue: 'bg-blue-500',
                            yellow: 'bg-yellow-400',
                            orange: 'bg-orange-500',
                            green: 'bg-green-500',
                            red: 'bg-red-500',
                            purple: 'bg-purple-500',
                        },

                        form: this.emptyForm(),

                        selected: null,

                        newItem: '',

                        saving: false,

                        dragging: false,

                        boardHeight: 480,

                        draft: @json($draft),

                        openCardId: @json(request()->integer('card') ?: null),
                    };
                },

                mounted() {
                    this.fitBoard();

                    window.addEventListener('resize', this.fitBoard);

                    // A card prepared by the AI assistant: the person reviews it and creates it.
                    if (this.draft && this.can.create) {
                        this.form = {
                            ...this.emptyForm(this.draft.column_id ?? this.columns[0]?.id ?? null),
                            ...this.draft,
                            column_id: this.draft.column_id ?? this.columns[0]?.id ?? null,
                            items_text: (this.draft.items ?? []).join('\n'),
                        };

                        this.$nextTick(() => this.$refs.createModal.open());
                    }

                    // A card found by the AI search opens straight in its drawer.
                    const card = this.openCardId && this.columns.flatMap((column) => column.cards).find((item) => item.id === this.openCardId);

                    if (card) {
                        this.$nextTick(() => this.openCard(card));
                    }
                },

                beforeUnmount() {
                    window.removeEventListener('resize', this.fitBoard);
                },

                methods: {
                    /**
                     * Fits the board in the space left below the header and filters, which wrap
                     * on narrow screens; on very short screens the page scrolls instead.
                     */
                    fitBoard() {
                        const board = this.$refs.board;

                        if (! board) {
                            return;
                        }

                        const top = board.getBoundingClientRect().top + window.scrollY;

                        this.boardHeight = Math.max(320, Math.floor(window.innerHeight - top - 16));

                        this.$nextTick(() => {
                            const overflow = document.documentElement.scrollHeight - window.innerHeight;

                            if (overflow > 0 && this.boardHeight - overflow >= 320) {
                                this.boardHeight -= overflow;
                            }
                        });
                    },

                    emptyForm(columnId = null) {
                        return {
                            column_id: columnId,
                            title: '',
                            description: '',
                            employee_id: '',
                            work_site_id: '',
                            assigned_to: '',
                            due_date: '',
                            items_text: '',
                        };
                    },

                    dotColor(color) {
                        return this.dotColors[color] ?? this.dotColors.gray;
                    },

                    columnLabel(columnId) {
                        return this.columns.find((column) => column.id === columnId)?.label ?? '';
                    },

                    completedText(record) {
                        return "@lang('human_resources::checklists.completed_by', ['date' => '__DATE__', 'name' => '__NAME__'])"
                            .replace('__DATE__', record.completed_label)
                            .replace('__NAME__', record.completed_by || '-');
                    },

                    url(route, id, itemId = null) {
                        return route.replace('__ID__', id).replace('__ITEM__', itemId);
                    },

                    fields(source) {
                        return {
                            title: source.title,
                            description: source.description || null,
                            employee_id: source.employee_id || null,
                            work_site_id: source.work_site_id || null,
                            assigned_to: source.assigned_to || null,
                            due_date: source.due_date || null,
                        };
                    },

                    flash(type, message) {
                        this.$emitter.emit('add-flash', { type, message });
                    },

                    fail(error) {
                        const errors = error.response?.data?.errors;

                        this.flash('error', errors ? Object.values(errors)[0][0] : (error.response?.data?.message ?? "@lang('human_resources::checklists.messages.error')"));
                    },

                    openCreate(column) {
                        this.form = this.emptyForm(column.id);

                        this.$refs.createModal.open();
                    },

                    create() {
                        this.saving = true;

                        this.$axios.post("{{ route('admin.human_resources.checklists.store') }}", {
                            ...this.fields(this.form),
                            column_id: this.form.column_id,
                            items: this.form.items_text.split('\n').map((line) => line.trim()).filter((line) => line.length),
                        })
                            .then((response) => {
                                this.columns.find((column) => column.id === response.data.card.column_id)?.cards.push(response.data.card);

                                this.$refs.createModal.close();

                                this.flash('success', response.data.message);
                            })
                            .catch(this.fail)
                            .finally(() => this.saving = false);
                    },

                    /**
                     * A copy for the drawer, with empty links as '' so the "none" options stay selected.
                     */
                    editable(card) {
                        const copy = JSON.parse(JSON.stringify(card));

                        ['description', 'employee_id', 'work_site_id', 'assigned_to', 'due_date'].forEach((field) => copy[field] ??= '');

                        return copy;
                    },

                    openCard(card) {
                        this.selected = this.editable(card);

                        this.newItem = '';

                        this.$refs.cardDrawer.open();
                    },

                    replaceCard(card, full = false) {
                        for (const column of this.columns) {
                            const index = column.cards.findIndex((item) => item.id === card.id);

                            if (index !== -1) {
                                if (column.id === card.column_id) {
                                    column.cards.splice(index, 1, card);
                                } else {
                                    column.cards.splice(index, 1);

                                    this.columns.find((target) => target.id === card.column_id)?.cards.push(card);
                                }

                                break;
                            }
                        }

                        if (this.selected?.id !== card.id) {
                            return;
                        }

                        // Item changes keep what is being typed in the details form.
                        if (full) {
                            this.selected = this.editable(card);
                        } else {
                            ['column_id', 'items', 'items_done', 'items_total', 'completed_label', 'completed_by'].forEach((field) => this.selected[field] = JSON.parse(JSON.stringify(card[field])));
                        }
                    },

                    saveCard() {
                        this.saving = true;

                        this.$axios.put(this.url("{{ route('admin.human_resources.checklists.update', '__ID__') }}", this.selected.id), this.fields(this.selected))
                            .then((response) => {
                                this.replaceCard(response.data.card, true);

                                this.flash('success', response.data.message);
                            })
                            .catch(this.fail)
                            .finally(() => this.saving = false);
                    },

                    deleteCard() {
                        const card = this.selected;

                        this.$emitter.emit('open-confirm-modal', {
                            message: "{!! trans('human_resources::checklists.delete_confirm') !!}",

                            agree: () => {
                                this.$axios.delete(this.url("{{ route('admin.human_resources.checklists.delete', '__ID__') }}", card.id))
                                    .then((response) => {
                                        this.columns.forEach((column) => column.cards = column.cards.filter((item) => item.id !== card.id));

                                        this.selected = null;

                                        this.$refs.cardDrawer.close();

                                        this.flash('success', response.data.message);
                                    })
                                    .catch(this.fail);
                            },
                        });
                    },

                    addItem() {
                        const title = this.newItem.trim();

                        if (! title) {
                            return;
                        }

                        this.$axios.post(this.url("{{ route('admin.human_resources.checklists.items.store', '__ID__') }}", this.selected.id), { title })
                            .then((response) => {
                                this.newItem = '';

                                this.replaceCard(response.data.card);
                            })
                            .catch(this.fail);
                    },

                    toggleItem(item) {
                        this.$axios.put(this.url("{{ route('admin.human_resources.checklists.items.update', ['id' => '__ID__', 'itemId' => '__ITEM__']) }}", this.selected.id, item.id), { completed: ! item.completed })
                            .then((response) => this.replaceCard(response.data.card))
                            .catch(this.fail);
                    },

                    removeItem(item) {
                        this.$axios.delete(this.url("{{ route('admin.human_resources.checklists.items.delete', ['id' => '__ID__', 'itemId' => '__ITEM__']) }}", this.selected.id, item.id))
                            .then((response) => this.replaceCard(response.data.card))
                            .catch(this.fail);
                    },

                    /**
                     * Saves the order of the column that received or reordered a card,
                     * the way the AcadFlow board does after a drag.
                     */
                    handleChange(column, event) {
                        if (! event.added && ! event.moved) {
                            return;
                        }

                        const cards = column.cards.map((card, position) => ({ id: card.id, column_id: column.id, position }));

                        this.$axios.post("{{ route('admin.human_resources.checklists.move') }}", { cards })
                            .then((response) => response.data.cards.forEach((card) => this.replaceCard(card)))
                            .catch((error) => {
                                this.fail(error);

                                window.location.reload();
                            });
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
