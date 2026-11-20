<x-admin::layouts>
    <x-slot:title>
        @lang('human_resources::checklists.settings.title')
    </x-slot>

    <x-admin::form
        :action="route('admin.settings.checklist_board.update')"
        method="PUT"
    >
        <!-- Header -->
        <div class="flex flex-col gap-2 rounded-lg border border-gray-300 bg-white text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex items-center justify-between gap-4 px-4 py-2 max-sm:flex-wrap">
                <div class="flex flex-col gap-1">
                    <a
                        href="{{ route('admin.settings.index') }}"
                        class="text-xs text-gray-600 hover:underline dark:text-gray-300"
                    >
                        @lang('admin::app.settings.title')
                    </a>

                    <div class="text-xl font-bold dark:text-white">
                        @lang('human_resources::checklists.settings.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    @if (bouncer()->hasPermission('checklists'))
                        <a
                            href="{{ route('admin.human_resources.checklists.index') }}"
                            class="transparent-button"
                        >
                            @lang('human_resources::checklists.settings.open_board')
                        </a>
                    @endif

                    <button
                        type="submit"
                        class="primary-button"
                    >
                        @lang('human_resources::checklists.settings.save')
                    </button>
                </div>
            </div>

            <p class="border-t border-gray-300 px-4 py-3 text-gray-600 dark:border-gray-800 dark:text-gray-300">
                @lang('human_resources::checklists.settings.hint')
            </p>

            @error('columns')
                <p class="px-4 pb-3 text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-2.5 overflow-auto py-3.5 max-xl:flex-wrap">
            <v-checklist-columns-settings>
                <x-admin::shimmer.pipelines.kanban />
            </v-checklist-columns-settings>
        </div>
    </x-admin::form>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-checklist-columns-settings-template"
        >
            <div class="flex gap-4">
                <draggable
                    tag="div"
                    ghost-class="draggable-ghost"
                    handle=".icon-move"
                    v-bind="{ animation: 200 }"
                    item-key="uid"
                    :list="columns"
                    class="flex gap-4"
                >
                    <!--
                        Fields are named by the column's uid, not its position, as the pipeline
                        stages are: a validated field keeps its value by name, so a position-based
                        name would leave the old label behind when a column is dragged. The order
                        saved is the order of the fields in the form.
                    -->
                    <template #item="{ element }">
                        <div class="flex min-w-[275px] max-w-[275px] flex-col justify-between rounded-lg border border-gray-300 bg-white dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex flex-col gap-6 px-4 py-3">
                                <!-- Column title and drag handle -->
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-2 py-1 font-medium dark:text-gray-300">
                                        <span
                                            class="h-2.5 w-2.5 rounded-full"
                                            :class="dotColor(element.color)"
                                        ></span>

                                        @{{ element.label || "@lang('human_resources::checklists.settings.new_column')" }}
                                    </span>

                                    <i class="icon-move cursor-grab rounded-md p-1 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"></i>
                                </div>

                                <div>
                                    <input
                                        type="hidden"
                                        :name="'columns[' + element.uid + '][id]'"
                                        :value="element.id || ''"
                                    />

                                    <!-- Name -->
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            @lang('human_resources::checklists.settings.name')
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="text"
                                            ::name="'columns[' + element.uid + '][label]'"
                                            v-model="element.label"
                                            ::rules="{ required: true, max: 60, unique_column: columns }"
                                            :label="trans('human_resources::checklists.settings.name')"
                                        />

                                        <x-admin::form.control-group.error ::name="'columns[' + element.uid + '][label]'" />
                                    </x-admin::form.control-group>

                                    <!-- Colour -->
                                    <x-admin::form.control-group class="!mb-0">
                                        <x-admin::form.control-group.label>
                                            @lang('human_resources::checklists.settings.color')
                                        </x-admin::form.control-group.label>

                                        <select
                                            :name="'columns[' + element.uid + '][color]'"
                                            v-model="element.color"
                                            class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                        >
                                            <option
                                                v-for="color in colors"
                                                :key="color"
                                                :value="color"
                                            >
                                                @{{ colorNames[color] }}
                                            </option>
                                        </select>
                                    </x-admin::form.control-group>

                                    <!-- Completes the cards: same look as the CRM switch -->
                                    <div class="mt-4 flex items-start justify-between gap-3">
                                        <label
                                            class="flex flex-col gap-0.5 text-sm text-gray-800 dark:text-white"
                                            :for="'completes-' + element.uid"
                                        >
                                            @lang('human_resources::checklists.settings.completes')

                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                @lang('human_resources::checklists.settings.completes_hint')
                                            </span>
                                        </label>

                                        <label class="relative mt-0.5 inline-flex shrink-0 cursor-pointer items-center">
                                            <input
                                                type="hidden"
                                                :name="'columns[' + element.uid + '][completes]'"
                                                value="0"
                                            />

                                            <input
                                                type="checkbox"
                                                class="peer sr-only"
                                                :id="'completes-' + element.uid"
                                                :name="'columns[' + element.uid + '][completes]'"
                                                value="1"
                                                v-model="element.completes"
                                            />

                                            <span class="peer h-5 w-9 cursor-pointer rounded-full bg-gray-200 after:absolute after:top-0.5 after:h-4 after:w-4 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-brandColor peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-blue-300 dark:bg-gray-800 dark:after:border-white dark:after:bg-white dark:peer-checked:bg-gray-950 after:ltr:left-0.5 peer-checked:after:ltr:translate-x-full after:rtl:right-0.5 peer-checked:after:rtl:-translate-x-full"></span>
                                        </label>
                                    </div>

                                    <p
                                        class="mt-4 text-xs text-gray-500 dark:text-gray-400"
                                        v-if="element.id"
                                    >
                                        @{{ "@lang('human_resources::checklists.settings.cards_count', ['count' => '__COUNT__'])".replace('__COUNT__', element.cards) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Remove column: any column, while another one remains -->
                            <div
                                class="flex cursor-pointer items-center gap-2 border-t border-gray-300 p-2 text-red-600 dark:border-gray-800"
                                v-if="columns.length > 1"
                                @click="remove(element)"
                            >
                                <i class="icon-delete text-2xl"></i>

                                @lang('human_resources::checklists.settings.remove')
                            </div>

                            <div
                                class="flex items-center gap-2 border-t border-gray-300 p-2 text-gray-500 dark:border-gray-800 dark:text-gray-400"
                                v-else
                            >
                                <i class="icon-info text-2xl"></i>

                                @lang('human_resources::checklists.settings.last_column')
                            </div>
                        </div>
                    </template>
                </draggable>

                <!-- Add column -->
                <div class="flex min-h-[400px] min-w-[275px] max-w-[275px] flex-col items-center justify-center gap-1 rounded-lg border border-gray-300 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div class="grid justify-center justify-items-center gap-3.5 px-4 py-3 text-center">
                        <div class="flex flex-col items-center gap-2">
                            <p class="text-xl font-semibold dark:text-gray-300">
                                @lang('human_resources::checklists.settings.add_title')
                            </p>

                            <p class="text-gray-400">
                                @lang('human_resources::checklists.settings.add_info')
                            </p>
                        </div>

                        <button
                            type="button"
                            class="secondary-button"
                            @click="add"
                        >
                            @lang('human_resources::checklists.settings.add')
                        </button>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-checklist-columns-settings', {
                template: '#v-checklist-columns-settings-template',

                data() {
                    return {
                        columns: @json($columns).map((column) => ({ ...column, uid: 'column_' + column.id })),

                        colors: @json($colors),

                        colorNames: @json(collect($colors)->mapWithKeys(fn ($color) => [$color => trans('human_resources::checklists.colors.'.$color)])),

                        dotColors: {
                            gray: 'bg-gray-400',
                            blue: 'bg-blue-500',
                            yellow: 'bg-yellow-400',
                            orange: 'bg-orange-500',
                            green: 'bg-green-500',
                            red: 'bg-red-500',
                            purple: 'bg-purple-500',
                        },

                        added: 0,
                    };
                },

                created() {
                    defineRule('unique_column', (value, columns) => {
                        if (! value || ! value.trim().length) {
                            return true;
                        }

                        const same = columns.filter((column) => (column.label || '').trim().toLowerCase() === value.trim().toLowerCase());

                        return same.length > 1
                            ? "{!! trans('human_resources::checklists.settings.duplicate') !!}"
                            : true;
                    });
                },

                methods: {
                    dotColor(color) {
                        return this.dotColors[color] ?? this.dotColors.gray;
                    },

                    add() {
                        this.columns.push({
                            uid: 'new_' + this.added++,
                            id: null,
                            key: null,
                            label: '',
                            color: 'gray',
                            completes: false,
                            cards: 0,
                        });
                    },

                    remove(column) {
                        this.$emitter.emit('open-confirm-modal', {
                            message: column.cards
                                ? "{!! trans('human_resources::checklists.settings.remove_confirm_cards') !!}"
                                : "{!! trans('human_resources::checklists.settings.remove_confirm') !!}",

                            agree: () => {
                                this.columns = this.columns.filter((item) => item.uid !== column.uid);
                            },
                        });
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
