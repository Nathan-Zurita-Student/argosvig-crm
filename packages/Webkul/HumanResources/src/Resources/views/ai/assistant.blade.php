<v-crm-assistant></v-crm-assistant>

@pushOnce('scripts')
    <script type="text/x-template" id="v-crm-assistant-template">
        <button type="button" class="secondary-button whitespace-nowrap max-sm:!px-2" @click="$refs.dialog.showModal()" aria-label="@lang('human_resources::ai.assistant')">
            <span aria-hidden="true">✦</span>
            <span class="max-sm:hidden">@lang('human_resources::ai.assistant_short')</span>
        </button>

        <dialog ref="dialog" aria-labelledby="crm-ai-title" class="crm-assistant-dialog rounded-lg border border-gray-300 bg-white p-4 text-gray-800 shadow-lg dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
            <div class="flex items-center justify-between gap-3">
                <h2 id="crm-ai-title" class="text-xl font-bold dark:text-white">@lang('human_resources::ai.assistant')</h2>
                <button type="button" class="secondary-button" @click="$refs.dialog.close()">@lang('human_resources::ai.close')</button>
            </div>

            <div v-if="earlierQuestions.length" class="mt-4 flex flex-col gap-2 text-sm text-gray-600 dark:text-gray-300">
                <div class="flex items-center justify-between gap-2">
                    <h3>@lang('human_resources::ai.previous_requests')</h3>
                    <button type="button" class="secondary-button" :disabled="busy" @click="reset">@lang('human_resources::ai.new_request')</button>
                </div>
                <ul>
                    <li v-for="(request, index) in earlierQuestions" :key="index" class="truncate" :title="request" v-text="request"></li>
                </ul>
            </div>

            <form class="mt-4 flex flex-col gap-3" @submit.prevent="ask">
                <label for="crm-ai-question">@lang('human_resources::ai.question')</label>
                <textarea id="crm-ai-question" v-model="question" rows="3" maxlength="2500" required class="w-full rounded-md border border-gray-300 bg-white p-2 dark:border-gray-800 dark:bg-gray-950"></textarea>
                <p class="text-sm text-gray-600 dark:text-gray-300">@lang('human_resources::ai.input_note')</p>
                <div v-if="!earlierQuestions.length" class="flex flex-wrap gap-2">
                    @foreach (['summary_prompt', 'draft_prompt', 'file_prompt'] as $prompt)
                        <button type="button" class="secondary-button" @click="question = @js(trans('human_resources::ai.'.$prompt))">@lang('human_resources::ai.'.$prompt.'_button')</button>
                    @endforeach
                </div>
                <div><button type="submit" class="primary-button" :disabled="busy">@lang('human_resources::ai.send')</button></div>
                <p v-if="busy" role="status">@lang('human_resources::ai.working')</p>
                <p v-if="error" v-text="error" role="alert" class="text-sm text-red-700 dark:text-red-300"></p>
            </form>

            <div v-if="result" class="mt-4 flex flex-col gap-3 border-t border-gray-200 pt-4 dark:border-gray-800" aria-live="polite">
                <p v-text="result.answer" class="whitespace-pre-wrap"></p>
                <div class="flex flex-wrap gap-2">
                    <a v-for="link in result.links" :key="link.url" class="secondary-button" :href="link.url" v-text="link.label"></a>
                </div>

                <form v-if="result.draft" method="POST" action="{{ route('admin.ai.draft') }}" data-turbo="false">
                    @csrf
                    <input type="hidden" name="token" :value="result.draft.token">
                    <button type="submit" class="primary-button" :disabled="busy">@lang('human_resources::ai.review_draft')</button>
                </form>

                <form v-if="result.file" method="POST" action="{{ route('admin.ai.download') }}" data-turbo="false" class="flex flex-col gap-2">
                    @csrf
                    <label for="crm-ai-file-title">@lang('human_resources::ai.file_title')</label>
                    <input id="crm-ai-file-title" name="title" v-model="result.file.title" maxlength="120" required class="rounded-md border border-gray-300 bg-white p-2 dark:border-gray-800 dark:bg-gray-950">
                    <label for="crm-ai-file-content">@lang('human_resources::ai.file_content')</label>
                    <textarea id="crm-ai-file-content" name="content" v-model="result.file.content" rows="6" maxlength="20000" required class="rounded-md border border-gray-300 bg-white p-2 dark:border-gray-800 dark:bg-gray-950"></textarea>
                    <div class="flex flex-wrap gap-2">
                        <select name="format" v-model="result.file.format" aria-label="@lang('human_resources::ai.file_format')" class="rounded-md border border-gray-300 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-950">
                            <option value="pdf">PDF</option>
                            <option value="txt">TXT</option>
                            <option value="md">Markdown</option>
                            <option value="csv">CSV</option>
                            <option value="xlsx">Excel (.xlsx)</option>
                        </select>
                        <button type="submit" class="primary-button" :disabled="busy">@lang('human_resources::ai.download')</button>
                    </div>
                </form>
            </div>
            @if (\Webkul\HumanResources\Services\OperationsBriefing::allowed())
                <a href="{{ route('admin.operations.index') }}" class="mt-4 inline-flex text-brandColor hover:underline dark:text-blue-300">@lang('human_resources::operations.title')</a>
            @endif
        </dialog>
    </script>

    <script type="module">
        app.component('v-crm-assistant', {
            template: '#v-crm-assistant-template',
            data() {
                return { question: '', earlierQuestions: [], busy: false, error: '', result: null, request: null };
            },
            beforeUnmount() {
                this.request?.abort();
            },
            methods: {
                reset() {
                    this.question = '';
                    this.earlierQuestions = [];
                    this.result = null;
                    this.error = '';
                },
                async ask() {
                    if (this.busy || !this.question.trim()) return;
                    const question = this.question.trim();
                    this.request = new AbortController();
                    this.busy = true;
                    this.error = '';
                    try {
                        const { data } = await this.$axios.post(@js(route('admin.ai.ask')), {
                            ...@js(app(\Webkul\HumanResources\Services\AssistantContext::class)->fromRequest()),
                            question,
                            earlier_questions: this.earlierQuestions,
                        }, { signal: this.request.signal, headers: { Accept: 'application/json' } });
                        this.earlierQuestions = [...this.earlierQuestions, question].slice(-3);
                        if (this.question.trim() === question) this.question = '';
                        this.result = data;
                    } catch (error) {
                        this.error = error.response?.status === 422
                            ? error.response.data.message
                            : @js(trans('human_resources::ai.request_error'));
                    } finally {
                        this.busy = false;
                    }
                },
            },
        });
    </script>
@endPushOnce
