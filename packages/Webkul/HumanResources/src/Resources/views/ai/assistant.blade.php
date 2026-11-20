@php
    $assistantAi = app(\Webkul\Core\Services\CrmAi::class);

    $assistant = [
        'context' => app(\Webkul\HumanResources\Services\AssistantContext::class)->fromRequest(),
        'askUrl' => route('admin.ai.ask'),
        'model' => $assistantAi->label(),
        'settingsUrl' => bouncer()->hasPermission('configuration')
            ? route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'crm_ai'])
            : null,
        'importUrl' => route('admin.ai.import'),
        'maxKilobytes' => \Webkul\HumanResources\Services\Ai\AssistantFiles::MAX_KILOBYTES,
        'accept' => collect(\Webkul\HumanResources\Services\Ai\AssistantFiles::EXTENSIONS)->map(fn ($extension) => '.'.$extension)->implode(','),
        'text' => [
            'overview' => trans('human_resources::ai.context_overview'),
            'requestError' => trans('human_resources::ai.request_error'),
            'copied' => trans('human_resources::ai.copied'),
            'copy' => trans('human_resources::ai.copy'),
            'oneField' => trans_choice('human_resources::ai.draft_fields', 1),
            'manyFields' => trans_choice('human_resources::ai.draft_fields', 2, ['count' => ':count']),
            'tooBig' => trans('human_resources::ai.files.too_big'),
            'unsupported' => trans('human_resources::ai.files.unsupported'),
            'onlyOne' => trans('human_resources::ai.files.only_one'),
            'rows' => [trans_choice('human_resources::ai.files.rows', 1), trans_choice('human_resources::ai.files.rows', 2, ['count' => ':count'])],
            'found' => [trans_choice('human_resources::ai.search.found', 0), trans_choice('human_resources::ai.search.found', 1), trans_choice('human_resources::ai.search.found', 2, ['count' => ':count'])],
            'showing' => trans('human_resources::ai.search.showing', ['count' => \Webkul\HumanResources\Services\Ai\NaturalSearch::LIMIT]),
            'confirm' => trans('human_resources::ai.import.confirm', ['count' => ':count', 'type' => ':type']),
            'row' => trans('human_resources::ai.import.row', ['row' => ':row']),
        ],
        // Suggested tasks by page; a prompt ending in a space waits in the box for details.
        'actions' => collect([
            'default' => trans('human_resources::ai.actions'),
            'file' => trans('human_resources::ai.context_actions.file'),
            'email' => trans('human_resources::ai.context_actions.email'),
            'lead' => trans('human_resources::ai.context_actions.lead'),
            'incident' => trans('human_resources::ai.context_actions.incident'),
        ])->map(fn (array $group, string $name) => collect($name === 'file' ? $group : [...$group, ...trans('human_resources::ai.context_actions.general')])
            ->map(fn (array $action, string $key) => ['key' => $key, ...$action])->values()->all()),
    ];
@endphp

<v-crm-assistant :assistant='@json($assistant)'>
    <button
        type="button"
        class="secondary-button whitespace-nowrap max-sm:!px-2"
        aria-label="@lang('human_resources::ai.assistant')"
    >
        <span aria-hidden="true">✦</span>
        <span class="max-sm:hidden">@lang('human_resources::ai.assistant_short')</span>
    </button>
</v-crm-assistant>

@if (app(\Webkul\HumanResources\Services\OperationsAi::class)->transcriptionAvailable())
    @include('human_resources::ai.dictation')
@endif

@pushOnce('scripts')
    <script type="text/x-template" id="v-crm-assistant-template">
        <button
            type="button"
            class="secondary-button whitespace-nowrap max-sm:!px-2"
            :aria-label="@js(trans('human_resources::ai.assistant_open'))"
            :title="@js(trans('human_resources::ai.assistant_open'))"
            aria-haspopup="dialog"
            @click="open"
        >
            <span aria-hidden="true">✦</span>
            <span class="max-sm:hidden">@lang('human_resources::ai.assistant_short')</span>
        </button>

        <dialog
            ref="dialog"
            aria-labelledby="crm-ai-title"
            class="crm-assistant-dialog border-gray-200 bg-white text-gray-800 shadow-2xl dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 ltr:border-l rtl:border-r"
            @click="closeOnBackdrop"
            @close="isOpen = false"
            @dragenter="dragEnter"
            @dragover="dragOver"
            @dragleave="dragLeave"
            @drop="dropFile"
        >
            <!-- Shown while a file is dragged over the panel -->
            <div
                v-if="dragging"
                class="pointer-events-none absolute inset-3 z-20 flex flex-col items-center justify-center gap-1.5 rounded-lg border-2 border-dashed border-brandColor bg-white/95 px-6 text-center dark:border-blue-400 dark:bg-gray-900/95"
            >
                <span class="icon-attachment text-4xl text-brandColor dark:text-blue-300"></span>

                <p class="text-base font-semibold text-gray-800 dark:text-white">@lang('human_resources::ai.files.drop')</p>

                <p class="text-xs text-gray-500 dark:text-gray-400">@lang('human_resources::ai.files.hint')</p>
            </div>

            <!-- Header -->
            <header class="flex items-start justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brandColor text-lg text-white" aria-hidden="true">✦</span>

                    <div class="min-w-0">
                        <h2 id="crm-ai-title" class="text-base font-bold dark:text-white">@lang('human_resources::ai.assistant_title')</h2>

                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            @lang('human_resources::ai.context'):
                            <span class="font-medium text-gray-700 dark:text-gray-300" v-text="contextLabel"></span>
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <button
                        v-if="messages.length"
                        type="button"
                        class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-50 dark:text-gray-300 dark:hover:bg-gray-800"
                        :disabled="busy"
                        @click="reset"
                    >
                        @lang('human_resources::ai.new_request')
                    </button>

                    <button
                        type="button"
                        class="icon-cross-large rounded-lg p-1.5 text-2xl text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                        aria-label="@lang('human_resources::ai.close')"
                        @click="close"
                    ></button>
                </div>
            </header>

            <!-- Conversation -->
            <div
                ref="scroller"
                class="flex-1 overflow-y-auto px-5 py-5"
                role="log"
                aria-live="polite"
            >
                <!-- First visit: what the assistant can do here -->
                <div v-if="! messages.length" class="grid gap-5">
                    <div class="grid gap-1">
                        <p class="text-xl font-bold dark:text-white">@lang('human_resources::ai.welcome')</p>

                        <p class="text-sm text-gray-500 dark:text-gray-400">@lang('human_resources::ai.welcome_help')</p>
                    </div>

                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <button
                            v-for="action in suggestions"
                            :key="action.key"
                            type="button"
                            class="group flex flex-col gap-0.5 rounded-lg border border-gray-200 p-3.5 text-start transition-all hover:border-brandColor hover:bg-blue-50/60 dark:border-gray-800 dark:hover:border-blue-400 dark:hover:bg-gray-800"
                            @click="useAction(action)"
                        >
                            <span class="text-sm font-semibold text-gray-800 group-hover:text-brandColor dark:text-white dark:group-hover:text-blue-300" v-text="action.title"></span>

                            <span class="text-xs text-gray-500 dark:text-gray-400" v-text="action.help"></span>
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <div v-else class="grid gap-4">
                    <template v-for="message in messages" :key="message.id">
                        <!-- Request, with the file sent along -->
                        <div v-if="message.role === 'user'" class="flex flex-col items-end gap-1">
                            <p class="max-w-[85%] whitespace-pre-wrap rounded-2xl bg-brandColor px-4 py-2.5 text-sm text-white ltr:rounded-br-md rtl:rounded-bl-md" v-text="message.text"></p>

                            <span
                                v-if="message.attachment"
                                class="flex max-w-[85%] items-center gap-1 text-xs text-gray-500 dark:text-gray-400"
                            >
                                <span class="icon-attachment text-base"></span>
                                <span class="truncate" v-text="message.attachment"></span>
                            </span>
                        </div>

                        <!-- Failure, with the request kept for another try -->
                        <div
                            v-else-if="message.role === 'error'"
                            role="alert"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300"
                        >
                            <span v-text="message.text"></span>

                            <button
                                type="button"
                                class="font-semibold underline-offset-2 hover:underline disabled:opacity-50"
                                :disabled="busy"
                                @click="retry(message)"
                            >
                                @lang('human_resources::ai.retry')
                            </button>
                        </div>

                        <!-- Answer -->
                        <div v-else class="grid gap-3">
                            <div class="group relative rounded-2xl bg-gray-100 px-4 py-3 text-sm leading-relaxed dark:bg-gray-800 ltr:rounded-bl-md rtl:rounded-br-md">
                                <p class="whitespace-pre-wrap" v-text="message.answer"></p>

                                <button
                                    type="button"
                                    class="mt-2 text-xs font-semibold text-gray-500 hover:text-brandColor dark:text-gray-400 dark:hover:text-blue-300"
                                    @click="copy(message)"
                                    v-text="message.copied ? assistant.text.copied : assistant.text.copy"
                                ></button>
                            </div>

                            <!-- Records the answer is based on -->
                            <div v-if="message.links?.length" class="flex flex-wrap gap-2">
                                <a
                                    v-for="link in message.links"
                                    :key="link.url"
                                    :href="link.url"
                                    class="rounded-full border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 transition-colors hover:border-brandColor hover:text-brandColor dark:border-gray-700 dark:text-gray-300 dark:hover:text-blue-300"
                                    v-text="link.label"
                                ></a>
                            </div>

                            <!-- Records found by a search in plain language -->
                            <div
                                v-if="message.search"
                                class="grid gap-1 rounded-lg border border-gray-200 px-4 pb-2 pt-3 dark:border-gray-800"
                            >
                                <p class="text-sm font-semibold dark:text-white" v-text="message.search.label + ' · ' + foundText(message.search.total)"></p>

                                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                    <li
                                        v-for="item in message.search.items"
                                        :key="item.url"
                                    >
                                        <a
                                            :href="item.url"
                                            class="group flex flex-col gap-0.5 py-2"
                                        >
                                            <span class="text-sm font-medium text-brandColor group-hover:underline dark:text-blue-300" v-text="item.title"></span>

                                            <span
                                                v-if="item.details"
                                                class="text-xs text-gray-500 dark:text-gray-400"
                                                v-text="item.details"
                                            ></span>
                                        </a>
                                    </li>
                                </ul>

                                <p
                                    v-if="message.search.total > message.search.items.length"
                                    class="pb-1 text-xs text-gray-500 dark:text-gray-400"
                                    v-text="assistant.text.showing"
                                ></p>
                            </div>

                            <!-- Preview of records to create from the attached spreadsheet -->
                            <div
                                v-if="message.import"
                                class="grid gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-800"
                            >
                                <p class="text-sm font-semibold dark:text-white">
                                    @lang('human_resources::ai.import.preview') · <span v-text="message.import.label"></span>
                                </p>

                                <dl class="grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded bg-gray-50 px-2 py-2 dark:bg-gray-800">
                                        <dt class="text-xs text-gray-500 dark:text-gray-400">@lang('human_resources::ai.import.new')</dt>
                                        <dd class="text-lg font-semibold text-gray-800 dark:text-white" v-text="message.import.new"></dd>
                                    </div>

                                    <div class="rounded bg-gray-50 px-2 py-2 dark:bg-gray-800">
                                        <dt class="text-xs text-gray-500 dark:text-gray-400">@lang('human_resources::ai.import.existing')</dt>
                                        <dd class="text-lg font-semibold text-gray-800 dark:text-white" v-text="message.import.existing"></dd>
                                    </div>

                                    <div class="rounded bg-gray-50 px-2 py-2 dark:bg-gray-800">
                                        <dt class="text-xs text-gray-500 dark:text-gray-400">@lang('human_resources::ai.import.invalid')</dt>
                                        <dd
                                            class="text-lg font-semibold"
                                            :class="message.import.invalid ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-white'"
                                            v-text="message.import.invalid"
                                        ></dd>
                                    </div>
                                </dl>

                                <!-- First records, as they will be saved -->
                                <div
                                    v-if="message.import.sample.length"
                                    class="grid gap-1"
                                >
                                    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">@lang('human_resources::ai.import.sample')</p>

                                    <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-800">
                                        <table class="w-full text-left text-xs">
                                            <thead class="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                                <tr>
                                                    <th
                                                        v-for="column in Object.keys(message.import.sample[0])"
                                                        :key="column"
                                                        class="whitespace-nowrap px-2.5 py-1.5 font-medium"
                                                        v-text="column"
                                                    ></th>
                                                </tr>
                                            </thead>

                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                <tr
                                                    v-for="(record, index) in message.import.sample"
                                                    :key="index"
                                                >
                                                    <td
                                                        v-for="(value, column) in record"
                                                        :key="column"
                                                        class="whitespace-nowrap px-2.5 py-1.5 text-gray-700 dark:text-gray-300"
                                                        v-text="value || '—'"
                                                    ></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <details class="text-xs">
                                    <summary class="cursor-pointer font-medium text-gray-600 dark:text-gray-300">@lang('human_resources::ai.import.columns')</summary>

                                    <ul class="mt-1.5 grid gap-1 text-gray-600 dark:text-gray-300">
                                        <li
                                            v-for="column in message.import.columns"
                                            :key="column.field"
                                        >
                                            <span class="font-medium" v-text="column.field"></span> ← <span v-text="column.header"></span>
                                        </li>
                                    </ul>
                                </details>

                                <details
                                    v-if="message.import.errors.length"
                                    class="text-xs"
                                >
                                    <summary class="cursor-pointer font-medium text-red-700 dark:text-red-400">@lang('human_resources::ai.import.errors')</summary>

                                    <ul class="mt-1.5 grid gap-1 text-gray-600 dark:text-gray-300">
                                        <li
                                            v-for="error in message.import.errors"
                                            :key="error.row"
                                            v-text="assistant.text.row.replace(':row', error.row) + ': ' + error.message"
                                        ></li>
                                    </ul>
                                </details>

                                <p
                                    v-if="message.import.truncated"
                                    class="text-xs text-amber-700 dark:text-amber-300"
                                >@lang('human_resources::ai.import.truncated')</p>

                                <!-- Confirmation, then the result -->
                                <div
                                    v-if="! message.import.done"
                                    class="flex flex-wrap items-center gap-x-3 gap-y-1.5"
                                >
                                    <button
                                        type="button"
                                        class="primary-button"
                                        :disabled="message.import.saving || ! message.import.new"
                                        @click="confirmImport(message)"
                                        v-text="message.import.saving ? @js(trans('human_resources::ai.import.working')) : confirmText(message.import)"
                                    ></button>

                                    <span
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                        v-text="message.import.new ? @js(trans('human_resources::ai.import.confirm_help')) : @js(trans('human_resources::ai.import.nothing_new'))"
                                    ></span>
                                </div>

                                <div
                                    v-else
                                    role="status"
                                    class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-green-200 bg-green-50 px-3.5 py-2.5 text-sm text-green-900 dark:border-green-900/60 dark:bg-green-950/30 dark:text-green-200"
                                >
                                    <span v-text="message.import.done.message"></span>

                                    <a
                                        :href="message.import.done.url"
                                        class="font-semibold underline-offset-2 hover:underline"
                                    >@lang('human_resources::ai.import.open')</a>
                                </div>
                            </div>

                            <!-- Prefilled record, opened in its form for review -->
                            <form
                                v-if="message.draft"
                                method="POST"
                                action="{{ route('admin.ai.draft') }}"
                                data-turbo="false"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 dark:border-green-900/60 dark:bg-green-950/30"
                            >
                                @csrf

                                <input type="hidden" name="token" :value="message.draft.token">

                                <div class="grid gap-0.5">
                                    <p class="text-sm font-semibold text-green-900 dark:text-green-200">@lang('human_resources::ai.draft_ready')</p>

                                    <p class="text-xs text-green-800 dark:text-green-300" v-text="fieldsText(message.draft)"></p>
                                </div>

                                <button type="submit" class="primary-button">@lang('human_resources::ai.review_draft')</button>
                            </form>

                            <!-- Generated file -->
                            <form
                                v-if="message.file"
                                method="POST"
                                action="{{ route('admin.ai.download') }}"
                                data-turbo="false"
                                class="grid gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-800"
                            >
                                @csrf

                                <p class="text-sm font-semibold dark:text-white">@lang('human_resources::ai.file_ready')</p>

                                <label class="grid gap-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                                    @lang('human_resources::ai.file_title')

                                    <input
                                        name="title"
                                        v-model="message.file.title"
                                        maxlength="120"
                                        required
                                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-normal text-gray-800 dark:border-gray-600 dark:bg-gray-950 dark:text-gray-200"
                                    >
                                </label>

                                <details class="text-xs">
                                    <summary class="cursor-pointer font-medium text-gray-600 dark:text-gray-300">@lang('human_resources::ai.file_edit')</summary>

                                    <textarea
                                        name="content"
                                        v-model="message.file.content"
                                        rows="8"
                                        maxlength="20000"
                                        required
                                        :aria-label="@js(trans('human_resources::ai.file_content'))"
                                        class="mt-2 w-full rounded-lg border border-gray-300 bg-white p-2.5 font-mono text-xs text-gray-800 dark:border-gray-600 dark:bg-gray-950 dark:text-gray-200"
                                    ></textarea>
                                </details>

                                <fieldset class="flex flex-wrap gap-1.5">
                                    <legend class="sr-only">@lang('human_resources::ai.file_format')</legend>

                                    <label
                                        v-for="format in formats"
                                        :key="format.value"
                                        class="cursor-pointer rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors"
                                        :class="message.file.format === format.value
                                            ? 'border-brandColor bg-brandColor text-white'
                                            : 'border-gray-300 text-gray-600 hover:border-gray-400 dark:border-gray-700 dark:text-gray-300'"
                                    >
                                        <input
                                            type="radio"
                                            name="format"
                                            class="sr-only"
                                            :value="format.value"
                                            v-model="message.file.format"
                                        >
                                        <span v-text="format.label"></span>
                                    </label>
                                </fieldset>

                                <p
                                    v-if="['csv', 'xlsx'].includes(message.file.format)"
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >@lang('human_resources::ai.table_limit')</p>

                                <button type="submit" class="primary-button w-fit">@lang('human_resources::ai.download')</button>
                            </form>
                        </div>
                    </template>

                    <!-- Waiting for the answer -->
                    <div
                        v-if="busy"
                        role="status"
                        class="flex w-fit items-center gap-2 rounded-2xl bg-gray-100 px-4 py-3 text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400 ltr:rounded-bl-md rtl:rounded-br-md"
                    >
                        <span class="flex gap-1" aria-hidden="true">
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.3s]"></span>
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.15s]"></span>
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400"></span>
                        </span>

                        @lang('human_resources::ai.working')
                    </div>
                </div>
            </div>

            <!-- Composer -->
            <form
                class="grid gap-2 border-t border-gray-200 px-5 pb-4 pt-3 dark:border-gray-800"
                @submit.prevent="send"
            >
                <label for="crm-ai-question" class="sr-only">@lang('human_resources::ai.question')</label>

                <!-- Attached file: sent with the next request and kept for the follow-ups -->
                <div
                    v-if="attachment"
                    class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs dark:border-gray-800 dark:bg-gray-800"
                >
                    <span class="flex min-w-0 items-center gap-1.5 text-gray-700 dark:text-gray-200">
                        <span class="icon-attachment shrink-0 text-base"></span>
                        <span class="truncate font-medium" v-text="attachment.name"></span>
                        <span
                            v-if="attachment.rows"
                            class="shrink-0 text-gray-500 dark:text-gray-400"
                            v-text="'· ' + rowsText(attachment.rows)"
                        ></span>
                    </span>

                    <button
                        type="button"
                        class="icon-cross-large shrink-0 rounded p-0.5 text-lg text-gray-500 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700"
                        aria-label="@lang('human_resources::ai.files.remove')"
                        :disabled="busy"
                        @click="removeAttachment"
                    ></button>
                </div>

                <!-- What can be done with a file attached in the middle of a conversation -->
                <div
                    v-if="attachment && messages.length && ! question"
                    class="flex flex-wrap gap-1.5"
                >
                    <button
                        v-for="action in suggestions"
                        :key="action.key"
                        type="button"
                        class="rounded-full border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 transition-colors hover:border-brandColor hover:text-brandColor disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:text-blue-300"
                        :disabled="busy"
                        @click="useAction(action)"
                        v-text="action.title"
                    ></button>
                </div>

                <p
                    v-if="fileError"
                    role="alert"
                    class="text-xs text-red-700 dark:text-red-300"
                    v-text="fileError"
                ></p>

                <p
                    v-else-if="fileNotice"
                    role="status"
                    class="text-xs text-gray-500 dark:text-gray-400"
                    v-text="fileNotice"
                ></p>

                <div class="flex items-end gap-2 rounded-lg border border-gray-300 bg-white p-2 transition-colors focus-within:border-brandColor dark:border-gray-600 dark:bg-gray-950 dark:focus-within:border-blue-400">
                    <input
                        ref="file"
                        type="file"
                        class="hidden"
                        :accept="assistant.accept"
                        @change="pickFile"
                    >

                    <button
                        type="button"
                        class="icon-attachment shrink-0 rounded-md p-1.5 text-xl text-gray-500 transition-colors hover:bg-gray-100 hover:text-brandColor disabled:opacity-50 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-blue-300"
                        :aria-label="@js(trans('human_resources::ai.files.attach'))"
                        :title="@js(trans('human_resources::ai.files.attach').' · '.trans('human_resources::ai.files.hint'))"
                        :disabled="busy"
                        @click="$refs.file.click()"
                    ></button>

                    <textarea
                        id="crm-ai-question"
                        ref="input"
                        v-model="question"
                        rows="1"
                        maxlength="2500"
                        placeholder="@lang('human_resources::ai.placeholder')"
                        class="max-h-40 min-h-[2.25rem] flex-1 resize-none bg-transparent px-2 py-1.5 text-sm outline-none dark:text-gray-200"
                        @input="grow"
                        @keydown.enter.exact.prevent="send"
                        @paste="pasteFile"
                    ></textarea>

                    @if (app(\Webkul\HumanResources\Services\OperationsAi::class)->transcriptionAvailable())
                        <v-ai-dictation
                            placement="end"
                            :disabled="busy"
                            :remaining="Math.max(0, 2500 - question.length - (question ? 1 : 0))"
                            @busy-change="dictating = $event"
                            @transcript="appendTranscript"
                        ></v-ai-dictation>
                    @endif

                    <button
                        type="submit"
                        class="primary-button shrink-0 !px-3.5"
                        :disabled="busy || dictating || ! question.trim()"
                    >
                        @lang('human_resources::ai.send')
                    </button>
                </div>

                <p class="flex flex-wrap justify-between gap-x-3 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">
                    <span>@lang('human_resources::ai.input_note')</span>
                    <span class="max-sm:hidden">@lang('human_resources::ai.enter_hint')</span>
                </p>

                <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-400 dark:text-gray-500">
                    <span v-text="@js(trans('human_resources::ai.using_model', ['model' => ':model'])).replace(':model', assistant.model)"></span>

                    <a
                        v-if="assistant.settingsUrl"
                        :href="assistant.settingsUrl"
                        class="font-semibold text-brandColor hover:underline dark:text-blue-300"
                    >@lang('human_resources::ai.configure')</a>

                    @if (\Webkul\HumanResources\Services\OperationsBriefing::allowed())
                        <a
                            href="{{ route('admin.operations.index') }}"
                            class="font-semibold text-brandColor hover:underline dark:text-blue-300"
                        >@lang('human_resources::operations.title')</a>
                    @endif
                </p>
            </form>
        </dialog>
    </script>

    <script type="module">
        app.component('v-crm-assistant', {
            template: '#v-crm-assistant-template',

            props: {
                assistant: {
                    type: Object,
                    required: true,
                },
            },

            data() {
                return {
                    question: '',
                    dictating: false,
                    messages: [],
                    busy: false,
                    isOpen: false,
                    request: null,
                    contextLabel: '',

                    /**
                     * The attached file: `file` until it is sent, then the `token` the server
                     * keeps it under for the follow-up requests.
                     */
                    attachment: null,
                    fileError: '',
                    fileNotice: '',

                    /**
                     * A file is being dragged over the panel. Entering and leaving child
                     * elements fire their own events, hence the depth count.
                     */
                    dragging: false,
                    dragDepth: 0,
                    formats: [
                        { value: 'pdf', label: 'PDF' },
                        { value: 'xlsx', label: 'Excel' },
                        { value: 'csv', label: 'CSV' },
                        { value: 'txt', label: 'Texto' },
                        { value: 'md', label: 'Markdown' },
                    ],
                };
            },

            mounted() {
                // The page title names what the assistant is looking at, such as "Colaboradores".
                const title = document.title.split(' | ')[0].trim();

                this.contextLabel = Object.keys(this.assistant.context).length && title ? title : this.assistant.text.overview;

                document.addEventListener('keydown', this.shortcut);
            },

            beforeUnmount() {
                this.request?.abort();

                document.removeEventListener('keydown', this.shortcut);
            },

            computed: {
                /**
                 * Suggested tasks: for the attached file, or for the kind of page open.
                 */
                suggestions() {
                    const { actions, context } = this.assistant;

                    if (this.attachment) {
                        return actions.file.filter((action) => action.key !== 'file_import' || this.attachment.table);
                    }

                    return actions[context.type] ?? actions.default;
                },
            },

            methods: {
                appendTranscript(transcript) {
                    this.question = [this.question.trimEnd(), transcript].filter(Boolean).join(' ');
                    this.$nextTick(this.grow);
                },

                open() {
                    if (! this.$refs.dialog.open) {
                        this.$refs.dialog.showModal();
                    }

                    this.isOpen = true;

                    this.$nextTick(() => this.$refs.input?.focus());
                },

                close() {
                    this.$refs.dialog.close();
                },

                closeOnBackdrop(event) {
                    if (event.target === this.$refs.dialog) {
                        this.close();
                    }
                },

                /**
                 * Ctrl + J (or Cmd + J) opens and closes the assistant anywhere in the CRM.
                 */
                shortcut(event) {
                    if ((event.ctrlKey || event.metaKey) && ! event.altKey && event.key.toLowerCase() === 'j') {
                        event.preventDefault();

                        this.$refs.dialog.open ? this.close() : this.open();
                    }
                },

                reset() {
                    this.request?.abort();

                    this.question = '';

                    this.messages = [];

                    this.removeAttachment();

                    this.$nextTick(() => this.$refs.input?.focus());
                },

                pickFile(event) {
                    const file = event.target.files[0];

                    event.target.value = '';

                    this.attachFile(file);
                },

                hasFiles(event) {
                    return [...(event.dataTransfer?.types ?? [])].includes('Files');
                },

                dragEnter(event) {
                    if (! this.hasFiles(event) || this.busy) {
                        return;
                    }

                    event.preventDefault();

                    this.dragDepth++;

                    this.dragging = true;
                },

                dragOver(event) {
                    if (! this.hasFiles(event)) {
                        return;
                    }

                    // Without this the browser would open the file instead of dropping it here.
                    event.preventDefault();

                    event.dataTransfer.dropEffect = this.busy ? 'none' : 'copy';
                },

                dragLeave(event) {
                    if (! this.hasFiles(event)) {
                        return;
                    }

                    this.dragDepth = Math.max(0, this.dragDepth - 1);

                    if (! this.dragDepth) {
                        this.dragging = false;
                    }
                },

                dropFile(event) {
                    if (! this.hasFiles(event)) {
                        return;
                    }

                    event.preventDefault();

                    this.dragDepth = 0;

                    this.dragging = false;

                    if (this.busy) {
                        return;
                    }

                    const files = [...event.dataTransfer.files];

                    this.attachFile(files[0], files.length > 1);
                },

                /**
                 * A file or a screenshot pasted into the request box becomes the attachment.
                 */
                pasteFile(event) {
                    const files = [...(event.clipboardData?.files ?? [])];

                    if (! files.length || this.busy) {
                        return;
                    }

                    event.preventDefault();

                    this.attachFile(files[0], files.length > 1);
                },

                /**
                 * The one place every file comes through: the clip button, a drop or a paste.
                 */
                attachFile(file, several = false) {
                    this.fileError = '';

                    this.fileNotice = '';

                    if (! file) {
                        return;
                    }

                    const extension = '.' + file.name.split('.').pop().toLowerCase();

                    if (! this.assistant.accept.split(',').includes(extension)) {
                        this.fileError = this.assistant.text.unsupported;

                        return;
                    }

                    if (file.size > this.assistant.maxKilobytes * 1024) {
                        this.fileError = this.assistant.text.tooBig;

                        return;
                    }

                    this.attachment = {
                        file,
                        name: file.name,
                        token: null,
                        rows: null,
                        table: /\.(csv|xlsx?)$/i.test(file.name),
                    };

                    if (several) {
                        this.fileNotice = this.assistant.text.onlyOne;
                    }

                    this.$nextTick(() => this.$refs.input?.focus());
                },

                removeAttachment() {
                    this.attachment = null;

                    this.fileError = '';

                    this.fileNotice = '';
                },

                useAction(action) {
                    // A prompt ending in a space waits in the box for the details.
                    if (action.prompt.endsWith(' ')) {
                        this.question = action.prompt;

                        this.$nextTick(() => {
                            const input = this.$refs.input;

                            input.focus();

                            input.setSelectionRange(input.value.length, input.value.length);

                            this.grow();
                        });

                        return;
                    }

                    this.ask(action.prompt);
                },

                send() {
                    const question = this.question.trim();

                    if (! question || this.busy || this.dictating) {
                        return;
                    }

                    this.question = '';

                    this.$nextTick(this.grow);

                    this.ask(question);
                },

                retry(message) {
                    this.messages = this.messages.filter((item) => item !== message);

                    this.ask(message.question, true);
                },

                async ask(question, isRetry = false) {
                    if (this.busy) {
                        return;
                    }

                    // The server keeps up to three earlier requests for follow-up questions.
                    const earlier = this.messages
                        .filter((item) => item.role === 'user')
                        .map((item) => item.text)
                        .slice(isRetry ? -4 : -3);

                    const upload = this.attachment?.file ?? null;

                    if (isRetry) {
                        earlier.pop();
                    } else {
                        this.messages.push({ id: Date.now() + Math.random(), role: 'user', text: question, attachment: upload?.name ?? null });
                    }

                    this.busy = true;

                    this.request = new AbortController();

                    this.scrollDown();

                    const fields = {
                        ...this.assistant.context,
                        question,
                        earlier_questions: earlier.slice(-3),
                        file_token: this.attachment?.token ?? null,
                    };

                    // A new file goes as multipart; afterwards only its token travels.
                    let payload = fields;

                    if (upload) {
                        payload = new FormData();

                        Object.entries(fields).forEach(([key, value]) => {
                            if (Array.isArray(value)) {
                                value.forEach((item) => payload.append(key + '[]', item));
                            } else if (value !== null && value !== undefined) {
                                payload.append(key, value);
                            }
                        });

                        payload.append('attachment', upload);
                    }

                    try {
                        const { data } = await this.$axios.post(this.assistant.askUrl, payload, { signal: this.request.signal, headers: { Accept: 'application/json' } });

                        if (data.attachment && this.attachment) {
                            this.attachment = { ...this.attachment, file: null, token: data.attachment.token, rows: data.attachment.rows, table: data.attachment.rows !== null };
                        }

                        this.messages.push({
                            id: Date.now() + Math.random(),
                            role: 'assistant',
                            answer: data.answer,
                            links: data.links ?? [],
                            draft: data.draft,
                            file: data.file,
                            search: data.search,
                            import: data.import ? { ...data.import, saving: false, done: null } : null,
                            copied: false,
                        });
                    } catch (error) {
                        if (error.name === 'CanceledError') {
                            return;
                        }

                        this.messages.push({
                            id: Date.now() + Math.random(),
                            role: 'error',
                            question,
                            text: error.response?.status === 422
                                ? error.response.data.message
                                : this.assistant.text.requestError,
                        });
                    } finally {
                        this.busy = false;

                        this.scrollDown();
                    }
                },

                async copy(message) {
                    try {
                        await navigator.clipboard.writeText(message.answer);

                        message.copied = true;

                        setTimeout(() => (message.copied = false), 1600);
                    } catch (error) {}
                },

                fieldsText(draft) {
                    const count = Object.keys(draft.fields ?? {}).length;

                    return count === 1
                        ? this.assistant.text.oneField
                        : this.assistant.text.manyFields.replace(':count', count);
                },

                rowsText(count) {
                    return count === 1 ? this.assistant.text.rows[0] : this.assistant.text.rows[1].replace(':count', count);
                },

                foundText(count) {
                    const [none, one, many] = this.assistant.text.found;

                    return count === 0 ? none : (count === 1 ? one : many.replace(':count', count));
                },

                confirmText(preview) {
                    return this.assistant.text.confirm
                        .replace(':count', preview.new)
                        .replace(':type', preview.label.toLowerCase());
                },

                /**
                 * Creates the previewed records; the server checks everything again.
                 */
                async confirmImport(message) {
                    message.import.saving = true;

                    try {
                        const { data } = await this.$axios.post(this.assistant.importUrl, { token: message.import.token }, { headers: { Accept: 'application/json' } });

                        message.import.done = data;
                    } catch (error) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message ?? this.assistant.text.requestError,
                        });
                    } finally {
                        message.import.saving = false;

                        this.scrollDown();
                    }
                },

                grow() {
                    const input = this.$refs.input;

                    if (! input) {
                        return;
                    }

                    input.style.height = 'auto';

                    input.style.height = `${input.scrollHeight}px`;
                },

                scrollDown() {
                    this.$nextTick(() => {
                        const scroller = this.$refs.scroller;

                        if (scroller) {
                            scroller.scrollTop = scroller.scrollHeight;
                        }
                    });
                },
            },
        });
    </script>
@endPushOnce
