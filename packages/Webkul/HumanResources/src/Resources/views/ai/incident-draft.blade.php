@include('human_resources::ai.dictation')

<v-ai-incident-draft :apply-fields="setValues"></v-ai-incident-draft>

@pushOnce('scripts')
    <script type="text/x-template" id="v-ai-incident-draft-template">
        <section class="grid gap-3 rounded-lg border border-gray-300 bg-white p-4 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
            <div>
                <label for="incident-ai-narrative" class="block text-sm font-semibold">
                    @lang('human_resources::ai.incident_draft.label')
                </label>
                <p id="incident-ai-help" class="mt-1 text-sm leading-5 text-gray-600 dark:text-gray-300">
                    @lang('human_resources::ai.incident_draft.help')
                </p>
            </div>

            <textarea
                id="incident-ai-narrative"
                v-model="narrative"
                rows="3"
                maxlength="10000"
                aria-describedby="incident-ai-help"
                placeholder="@lang('human_resources::ai.incident_draft.placeholder')"
                class="w-full rounded-md border border-gray-300 bg-white p-2 text-sm dark:border-gray-700 dark:bg-gray-950"
            ></textarea>

            <div class="flex flex-wrap items-center gap-2">
                @if (app(\Webkul\HumanResources\Services\OperationsAi::class)->transcriptionAvailable())
                    <v-ai-dictation
                        :disabled="busy"
                        :remaining="Math.max(0, 10000 - narrative.length - (narrative ? 1 : 0))"
                        @busy-change="dictating = $event"
                        @transcript="narrative = [narrative.trimEnd(), $event].filter(Boolean).join(' ')"
                    ></v-ai-dictation>
                @endif

                <button
                    type="button"
                    class="secondary-button"
                    :disabled="busy || dictating || narrative.trim().length < 10"
                    @click="fill"
                >
                    ✦ <span v-text="busy ? @js(trans('human_resources::ai.working')) : @js(trans('human_resources::ai.incident_draft.fill'))"></span>
                </button>
            </div>

            <p v-if="error" role="alert" class="text-sm text-red-700 dark:text-red-300" v-text="error"></p>
            <p v-else-if="filled" role="status" class="text-sm text-gray-700 dark:text-gray-200">
                @lang('human_resources::operations.review_draft')
            </p>
        </section>
    </script>

    <script type="module">
        app.component('v-ai-incident-draft', {
            template: '#v-ai-incident-draft-template',
            props: { applyFields: { type: Function, required: true } },

            data() {
                return { narrative: '', busy: false, dictating: false, filled: false, error: '', request: null };
            },

            beforeUnmount() {
                this.request?.abort();
            },

            methods: {
                async fill() {
                    if (this.busy || this.dictating || this.narrative.trim().length < 10) {
                        return;
                    }

                    this.busy = true;
                    this.error = '';
                    this.filled = false;
                    this.request = new AbortController();

                    try {
                        const { data } = await this.$axios.post(@js(route('admin.ai.incident_draft')), {
                            narrative: this.narrative.trim(),
                        }, { signal: this.request.signal, headers: { Accept: 'application/json' } });

                        this.applyFields(data.fields);
                        this.filled = true;
                    } catch (error) {
                        if (! this.request.signal.aborted) {
                            this.error = error.response?.data?.message || @js(trans('human_resources::ai.request_error'));
                        }
                    } finally {
                        this.busy = false;
                    }
                },
            },
        });
    </script>
@endPushOnce
