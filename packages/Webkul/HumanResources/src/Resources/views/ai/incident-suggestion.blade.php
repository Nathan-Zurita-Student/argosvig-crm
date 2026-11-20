{{--
    Inside the incident form: reads the title and description being written and suggests the
    severity and an action plan, which the person applies to the form or ignores.
--}}
@if (bouncer()->hasPermission('crm-ai') && app(\Webkul\HumanResources\Services\OperationsAi::class)->available())
    <div class="md:col-span-2">
        <v-ai-incident-suggestion>
            <button
                type="button"
                class="secondary-button"
            >
                ✦ @lang('human_resources::ai.incident.suggest')
            </button>
        </v-ai-incident-suggestion>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-ai-incident-suggestion-template"
        >
            <div class="grid gap-2" ref="root">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <button
                        type="button"
                        class="secondary-button"
                        :disabled="busy"
                        @click="suggest"
                    >
                        ✦ <span v-text="busy ? @js(trans('human_resources::ai.working')) : @js(trans('human_resources::ai.incident.suggest'))"></span>
                    </button>

                    <p class="text-xs italic leading-5 text-gray-600 dark:text-gray-300">@lang('human_resources::ai.incident.suggest_help')</p>
                </div>

                <div
                    v-if="suggestion"
                    class="grid gap-3 rounded border border-gray-300 bg-gray-50 p-4 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-200"
                    role="status"
                >
                    <div
                        v-if="suggestion.severity"
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <p>
                            @lang('human_resources::ai.incident.severity'):
                            <span class="font-semibold" v-text="suggestion.severity_label"></span>
                            <span
                                v-if="suggestion.reason"
                                class="block text-xs italic text-gray-600 dark:text-gray-300"
                                v-text="suggestion.reason"
                            ></span>
                        </p>

                        <button
                            type="button"
                            class="secondary-button"
                            :disabled="applied.severity"
                            @click="applySeverity"
                            v-text="applied.severity ? @js(trans('human_resources::ai.incident.applied')) : @js(trans('human_resources::ai.incident.apply_severity'))"
                        ></button>
                    </div>

                    <div
                        v-if="suggestion.plan.length"
                        class="grid gap-2"
                    >
                        <p class="font-medium">@lang('human_resources::ai.incident.plan')</p>

                        <ol class="grid list-decimal gap-1 ltr:pl-5 rtl:pr-5">
                            <li
                                v-for="(step, index) in suggestion.plan"
                                :key="index"
                                v-text="step"
                            ></li>
                        </ol>

                        <button
                            type="button"
                            class="secondary-button w-fit"
                            :disabled="applied.plan"
                            @click="addPlan"
                            v-text="applied.plan ? @js(trans('human_resources::ai.incident.applied')) : @js(trans('human_resources::ai.incident.add_plan'))"
                        ></button>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-ai-incident-suggestion', {
                template: '#v-ai-incident-suggestion-template',

                data() {
                    return {
                        busy: false,
                        suggestion: null,
                        applied: { severity: false, plan: false },
                    };
                },

                methods: {
                    field(name) {
                        return this.$refs.root.closest('form')?.querySelector(`[name="${name}"]`);
                    },

                    /**
                     * Sets a form field and tells the form validation about it.
                     */
                    setField(name, value) {
                        const field = this.field(name);

                        if (! field) {
                            return;
                        }

                        field.value = value;

                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    },

                    async suggest() {
                        const description = this.field('description')?.value.trim() ?? '';

                        if (description.length < 10) {
                            this.$emitter.emit('add-flash', { type: 'warning', message: @js(trans('human_resources::ai.incident.needs_description')) });

                            return;
                        }

                        this.busy = true;

                        try {
                            const { data } = await this.$axios.post(@js(route('admin.ai.incident_suggestion')), {
                                title: this.field('title')?.value ?? '',
                                description,
                            }, { headers: { Accept: 'application/json' } });

                            this.suggestion = data;

                            this.applied = { severity: false, plan: false };
                        } catch (error) {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || @js(trans('human_resources::ai.request_error')) });
                        } finally {
                            this.busy = false;
                        }
                    },

                    applySeverity() {
                        this.setField('severity', this.suggestion.severity);

                        this.applied.severity = true;
                    },

                    addPlan() {
                        const current = this.field('description')?.value.trimEnd() ?? '';

                        const plan = this.suggestion.plan.map((step, index) => `${index + 1}. ${step}`).join('\n');

                        this.setField('description', `${current}\n\n${@js(trans('human_resources::ai.incident.plan'))}:\n${plan}`);

                        this.applied.plan = true;
                    },
                },
            });
        </script>
    @endPushOnce
@endif
