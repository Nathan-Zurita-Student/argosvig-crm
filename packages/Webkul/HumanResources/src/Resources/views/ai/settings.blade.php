@php
    $ai = app(\Webkul\Core\Services\CrmAi::class);
    $catalog = \Webkul\Core\Services\AiCatalog::class;
    $defaults = \Webkul\Core\Services\CrmAi::MODELS;
    $audioDefaults = \Webkul\Core\Services\CrmAi::AUDIO_MODELS;
    $saved = fn (string $name) => old('general.crm_ai.settings.'.$name) ?? system_config()->getConfigData(\Webkul\Core\Services\CrmAi::SETTINGS.$name);

    $providers = collect($catalog::PROVIDERS)->map(fn (array $provider, string $key) => [
        'key' => $key,
        'name' => trans('human_resources::ai.providers.'.$key),
        'company' => trans('human_resources::ai.companies.'.$key),
        'summary' => trans('human_resources::ai.provider_summary.'.$key),
        'keyUrl' => $provider['key_url'],
        'keyHint' => $provider['key_hint'],
        'keyLabel' => trans('human_resources::ai.credentials.'.$key),
        'transcribes' => $provider['transcribes'],
        'hasKey' => $ai->hasKey($key),
        'model' => $saved($key.'_model') ?: $defaults[$key],
        'audioModel' => isset($audioDefaults[$key]) ? ($saved($key.'_audio_model') ?: $audioDefaults[$key]) : null,
        'models' => array_map(fn (array $model) => [...$model, 'note' => trans($catalog::noteKey($model['id']))], $provider['models']),
        'audioModels' => array_map(fn (array $model) => [...$model, 'note' => trans($catalog::audioNoteKey($model['id']))], $catalog::audioModels($key)),
    ])->values();

    $payload = [
        'enabled' => (bool) ($saved('enable') ?? config('operations.ai_enabled') ?? true),
        'provider' => $ai->provider(),
        'audioProvider' => (string) $saved('audio_provider'),
        'providers' => $providers,
        'testUrl' => route('admin.ai.connection.test'),
        'canTest' => bouncer()->hasPermission('configuration'),
        'text' => [
            'tags' => trans('human_resources::ai.tags'),
            'levels' => trans('human_resources::ai.levels'),
            'reasoning' => trans('human_resources::ai.reasoning'),
            'speed' => trans('human_resources::ai.speed'),
            'cost' => trans('human_resources::ai.cost'),
            'keySaved' => trans('human_resources::ai.key_saved'),
            'keyPlaceholder' => trans('human_resources::ai.key_placeholder', ['hint' => ':hint']),
            'keyWhere' => trans('human_resources::ai.key_where', ['company' => ':company']),
            'otherModelHelp' => trans('human_resources::ai.other_model_help', ['company' => ':company']),
            'otherModel' => trans('human_resources::ai.other_model'),
            'otherAudioModelHelp' => trans('human_resources::ai.other_audio_model_help', ['company' => ':company']),
            'audioKeyNeeded' => trans('human_resources::ai.audio_key_needed', ['provider' => ':provider']),
            'sameProvider' => trans('human_resources::ai.same_provider'),
            'audioUnavailable' => trans('human_resources::ai.audio_unavailable'),
            'requestError' => trans('human_resources::ai.status.provider_error'),
        ],
    ];

    // The same classes as the configuration form fields, so this panel reads like the rest of the CRM.
    $labelClass = 'mb-1.5 flex items-center gap-1 text-sm font-normal text-gray-800 dark:text-white';
    $inputClass = 'w-full rounded border border-gray-300 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400';
    $infoClass = 'mt-1 block text-xs italic leading-5 text-gray-600 dark:text-gray-300';
@endphp

{{-- The configuration form uses these to find the validation rules of the submitted section. --}}
<input
    type="hidden"
    name="keys[]"
    value="{{ json_encode($child) }}"
/>

<v-crm-ai-settings :settings='@json($payload)'>
    <div class="grid gap-4">
        @foreach ([1, 2, 3] as $placeholder)
            <div>
                <div class="shimmer mb-1.5 h-5 w-24"></div>
                <div class="shimmer flex h-[38px] w-full rounded-md"></div>
            </div>
        @endforeach
    </div>
</v-crm-ai-settings>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-crm-ai-settings-template"
    >
        <div>
            <!-- Values saved by the configuration form -->
            <input type="hidden" name="general[crm_ai][settings][enable]" :value="enabled ? 1 : 0">
            <input type="hidden" name="general[crm_ai][settings][provider]" :value="provider">
            <input type="hidden" :name="field(provider + '_model')" :value="effectiveModel">
            <input type="hidden" name="general[crm_ai][settings][audio_provider]" :value="audioProvider">
            <input v-if="audioEffective" type="hidden" :name="field(audioEffective + '_audio_model')" :value="effectiveAudioModel">

            <!-- Enabled -->
            <div class="mb-4">
                <p class="{{ $labelClass }}">@lang('human_resources::ai.enabled')</p>

                <label class="relative inline-flex cursor-pointer items-center">
                    <input
                        id="crm-ai-enabled"
                        type="checkbox"
                        class="peer sr-only"
                        v-model="enabled"
                    >

                    <span class="peer h-5 w-9 cursor-pointer rounded-full bg-gray-200 after:absolute after:top-0.5 after:h-4 after:w-4 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-brandColor peer-checked:after:border-white dark:bg-gray-800 dark:after:border-white dark:after:bg-white dark:peer-checked:bg-gray-950 after:ltr:left-0.5 peer-checked:after:ltr:translate-x-full after:rtl:right-0.5 peer-checked:after:rtl:-translate-x-full"></span>

                    <span class="sr-only">@lang('human_resources::ai.enabled')</span>
                </label>

                <p class="{{ $infoClass }}">@lang('human_resources::ai.enabled_help')</p>
            </div>

            <!-- Provider -->
            <div class="mb-4">
                <label for="crm-ai-provider" class="{{ $labelClass }}">@lang('human_resources::ai.provider')</label>

                <select
                    id="crm-ai-provider"
                    v-model="provider"
                    class="custom-select {{ $inputClass }}"
                >
                    <option
                        v-for="item in settings.providers"
                        :key="item.key"
                        :value="item.key"
                        v-text="item.name + ' (' + item.company + ')' + (item.hasKey ? ' · ' + settings.text.keySaved : '')"
                    ></option>
                </select>

                <p class="{{ $infoClass }}" v-text="current.summary"></p>
            </div>

            <!-- Key -->
            <div class="mb-4">
                <label :for="'crm-ai-key-' + provider" class="{{ $labelClass }}" v-text="current.keyLabel"></label>

                <div class="relative">
                    <input
                        :id="'crm-ai-key-' + provider"
                        :key="provider"
                        :type="showKey ? 'text' : 'password'"
                        :name="field(provider + '_key')"
                        v-model="keys[provider]"
                        :placeholder="current.hasKey ? '•••••••••••• (' + settings.text.keySaved.toLowerCase() + ')' : settings.text.keyPlaceholder.replace(':hint', current.keyHint)"
                        autocomplete="off"
                        spellcheck="false"
                        maxlength="255"
                        class="{{ $inputClass }} ltr:pr-10 rtl:pl-10"
                    >

                    <button
                        type="button"
                        class="absolute top-1/2 -translate-y-1/2 text-xl text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 ltr:right-2.5 rtl:left-2.5"
                        :class="showKey ? 'icon-eye-hide' : 'icon-eye'"
                        :aria-label="showKey ? @js(trans('human_resources::ai.key_hide')) : @js(trans('human_resources::ai.key_show'))"
                        @click="showKey = ! showKey"
                    ></button>
                </div>

                <p class="{{ $infoClass }}">
                    <span v-text="current.hasKey ? @js(trans('human_resources::ai.keep_key')) : @js(trans('human_resources::ai.key_help'))"></span>

                    <a
                        :href="current.keyUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="not-italic text-brandColor hover:underline dark:text-blue-300 ltr:ml-1 rtl:mr-1"
                        v-text="settings.text.keyWhere.replace(':company', current.company)"
                    ></a>
                </p>
            </div>

            <!--
                Model: a select like the others, whose options also say what each model is
                good at and how it reasons. Only the models of the chosen AI are listed.
            -->
            <div class="mb-4">
                <p id="crm-ai-model-label" class="{{ $labelClass }}">@lang('human_resources::ai.step_model')</p>

                <div
                    ref="modelSelect"
                    class="relative"
                >
                    <button
                        ref="modelButton"
                        type="button"
                        class="custom-select {{ $inputClass }} block truncate text-start ltr:pr-9 rtl:pl-9"
                        aria-haspopup="listbox"
                        aria-labelledby="crm-ai-model-label"
                        :aria-expanded="modelMenuOpen ? 'true' : 'false'"
                        :aria-activedescendant="modelMenuOpen ? 'crm-ai-model-' + activeIndex : null"
                        @click="toggleModelMenu"
                        @keydown="onModelKeydown"
                        v-text="selectedModelLabel"
                    ></button>

                    <ul
                        v-if="modelMenuOpen"
                        role="listbox"
                        aria-labelledby="crm-ai-model-label"
                        class="absolute z-20 mt-1 max-h-[22rem] w-full overflow-y-auto rounded border border-gray-300 bg-white py-1 shadow-[0px_10px_20px_0px_#0000001F] dark:border-gray-800 dark:bg-gray-900"
                    >
                        <li class="px-3 pb-1.5 pt-1 text-xs text-gray-500 dark:text-gray-400">@lang('human_resources::ai.step_model_help')</li>

                        <li
                            v-for="(item, index) in modelOptions"
                            :key="item.id"
                            :id="'crm-ai-model-' + index"
                            ref="modelOption"
                            role="option"
                            :aria-selected="models[provider] === item.id ? 'true' : 'false'"
                            class="flex cursor-pointer items-start gap-2.5 px-3 py-2.5"
                            :class="[
                                index === activeIndex ? 'bg-gray-100 dark:bg-gray-800' : '',
                                item.id === OTHER ? 'border-t border-gray-200 dark:border-gray-800' : '',
                            ]"
                            @mouseenter="activeIndex = index"
                            @mousedown.prevent="chooseModel(item.id)"
                        >
                            <span
                                class="mt-0.5 w-4 shrink-0 text-base text-brandColor dark:text-blue-300"
                                :class="models[provider] === item.id ? 'icon-tick' : ''"
                                aria-hidden="true"
                            ></span>

                            <span class="grid min-w-0 gap-0.5">
                                <span class="text-sm text-gray-800 dark:text-white">
                                    <span class="font-medium" v-text="item.name"></span>

                                    <span
                                        v-if="item.tag"
                                        class="text-xs"
                                        :class="item.tag === 'recommended' ? 'font-medium text-brandColor dark:text-blue-300' : 'text-gray-500 dark:text-gray-400'"
                                        v-text="' · ' + settings.text.tags[item.tag]"
                                    ></span>
                                </span>

                                <span class="text-xs leading-5 text-gray-600 dark:text-gray-300" v-text="item.note"></span>

                                <span
                                    v-if="item.reasoning"
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                    v-text="specs(item)"
                                ></span>
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- A model released after the list -->
                <input
                    v-if="models[provider] === OTHER"
                    ref="customModel"
                    v-model.trim="customModels[provider]"
                    placeholder="@lang('human_resources::ai.other_model_placeholder')"
                    pattern="[A-Za-z0-9._\-]+"
                    maxlength="180"
                    :aria-label="settings.text.otherModel"
                    class="{{ $inputClass }} mt-2"
                >

                <!-- What the chosen model is good at -->
                <p class="{{ $infoClass }}" v-text="selectedModel ? selectedModel.note : settings.text.otherModelHelp.replace(':company', current.company)"></p>

                <p
                    v-if="selectedModel"
                    class="text-xs leading-5 text-gray-500 dark:text-gray-400"
                    v-text="specs(selectedModel)"
                ></p>
            </div>

            <!-- Audio transcription -->
            <div class="mb-4 border-t border-gray-200 pt-4 dark:border-gray-800">
                <label for="crm-ai-audio" class="{{ $labelClass }}">@lang('human_resources::ai.audio_title')</label>

                <select
                    id="crm-ai-audio"
                    v-model="audioProvider"
                    class="custom-select {{ $inputClass }}"
                >
                    <option
                        value=""
                        v-text="current.transcribes ? settings.text.sameProvider + ' (' + current.name + ')' : settings.text.audioUnavailable"
                    ></option>

                    <option
                        v-for="item in settings.providers.filter((entry) => entry.transcribes && entry.key !== provider)"
                        :key="item.key"
                        :value="item.key"
                        v-text="item.name + ' (' + item.company + ')'"
                    ></option>
                </select>

                <p class="{{ $infoClass }}">
                    @lang('human_resources::ai.audio_intro')

                    <span v-if="! current.transcribes">@lang('human_resources::ai.audio_provider_help')</span>
                </p>
            </div>

            <!-- Key of a separate transcription provider that has none saved -->
            <div
                v-if="needsAudioKey"
                class="mb-4"
            >
                <label for="crm-ai-audio-key" class="{{ $labelClass }}" v-text="providerByKey(audioEffective).keyLabel"></label>

                <input
                    id="crm-ai-audio-key"
                    type="password"
                    :name="field(audioEffective + '_key')"
                    v-model="audioKey"
                    :placeholder="settings.text.keyPlaceholder.replace(':hint', providerByKey(audioEffective).keyHint)"
                    autocomplete="off"
                    spellcheck="false"
                    maxlength="255"
                    class="{{ $inputClass }}"
                >

                <p class="{{ $infoClass }}" v-text="settings.text.audioKeyNeeded.replace(':provider', providerByKey(audioEffective).name)"></p>
            </div>

            <div
                v-if="audioEffective"
                class="mb-4"
            >
                <label for="crm-ai-audio-model" class="{{ $labelClass }}">@lang('human_resources::ai.audio_model')</label>

                <select
                    id="crm-ai-audio-model"
                    v-model="audioChoices[audioEffective]"
                    class="custom-select {{ $inputClass }}"
                >
                    <option
                        v-for="item in audioProviderInfo.audioModels"
                        :key="item.id"
                        :value="item.id"
                        v-text="item.name + (item.tag ? ' · ' + settings.text.tags[item.tag] : '')"
                    ></option>

                    <option :value="OTHER" v-text="settings.text.otherModel"></option>
                </select>

                <input
                    v-if="audioChoices[audioEffective] === OTHER"
                    v-model.trim="customAudioModels[audioEffective]"
                    placeholder="@lang('human_resources::ai.other_model_placeholder')"
                    pattern="[A-Za-z0-9._\-]+"
                    maxlength="180"
                    :aria-label="settings.text.otherModel"
                    class="{{ $inputClass }} mt-2"
                >

                <p
                    class="{{ $infoClass }}"
                    v-text="audioChoices[audioEffective] === OTHER
                        ? settings.text.otherAudioModelHelp.replace(':company', audioProviderInfo.company)
                        : audioProviderInfo.audioModels.find((item) => item.id === audioChoices[audioEffective])?.note"
                ></p>
            </div>

            <!-- Connection test, on the saved settings -->
            <div
                v-if="settings.canTest"
                class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-gray-200 pt-4 dark:border-gray-800"
            >
                <button
                    type="button"
                    class="secondary-button"
                    :disabled="testing || isDirty"
                    @click="test"
                    v-text="testing ? @js(trans('human_resources::ai.testing')) : @js(trans('human_resources::ai.test'))"
                ></button>

                <p
                    class="text-xs italic leading-5"
                    :class="isDirty ? 'text-amber-700 dark:text-amber-300' : 'text-gray-600 dark:text-gray-300'"
                    v-text="isDirty ? @js(trans('human_resources::ai.unsaved')) : @js(trans('human_resources::ai.test_scope'))"
                ></p>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-crm-ai-settings', {
            template: '#v-crm-ai-settings-template',

            props: {
                settings: {
                    type: Object,
                    required: true,
                },
            },

            data() {
                const OTHER = '__other';

                const models = {};

                const customModels = {};

                const audioChoices = {};

                const customAudioModels = {};

                this.settings.providers.forEach((item) => {
                    const known = item.models.some((model) => model.id === item.model);

                    models[item.key] = known ? item.model : OTHER;

                    customModels[item.key] = known ? '' : item.model;

                    if (item.audioModel) {
                        const knownAudio = item.audioModels.some((model) => model.id === item.audioModel);

                        audioChoices[item.key] = knownAudio ? item.audioModel : OTHER;

                        customAudioModels[item.key] = knownAudio ? '' : item.audioModel;
                    }
                });

                return {
                    OTHER,
                    enabled: this.settings.enabled,
                    provider: this.settings.provider,
                    audioProvider: this.settings.audioProvider,
                    models,
                    customModels,
                    audioChoices,
                    customAudioModels,
                    keys: {},
                    audioKey: '',
                    showKey: false,
                    testing: false,
                    initial: '',
                    modelMenuOpen: false,
                    activeIndex: 0,
                };
            },

            mounted() {
                this.initial = this.snapshot();

                document.addEventListener('click', this.closeModelMenuOutside);
            },

            beforeUnmount() {
                document.removeEventListener('click', this.closeModelMenuOutside);
            },

            computed: {
                current() {
                    return this.providerByKey(this.provider);
                },

                /**
                 * Options of the model select: the catalog of the chosen AI, then "Other model".
                 */
                modelOptions() {
                    return [
                        ...this.current.models,
                        {
                            id: this.OTHER,
                            name: this.settings.text.otherModel,
                            tag: null,
                            note: this.settings.text.otherModelHelp.replace(':company', this.current.company),
                        },
                    ];
                },

                selectedModel() {
                    return this.current.models.find((item) => item.id === this.models[this.provider]) ?? null;
                },

                selectedModelLabel() {
                    if (! this.selectedModel) {
                        return this.settings.text.otherModel;
                    }

                    const tag = this.selectedModel.tag ? ' · ' + this.settings.text.tags[this.selectedModel.tag] : '';

                    return this.selectedModel.name + tag;
                },

                effectiveModel() {
                    return this.models[this.provider] === this.OTHER
                        ? this.customModels[this.provider]
                        : this.models[this.provider];
                },

                /**
                 * Who transcribes audio: the chosen provider, or the text provider itself when
                 * it can; nobody when Claude is used alone.
                 */
                audioEffective() {
                    if (this.audioProvider) {
                        return this.audioProvider;
                    }

                    return this.current.transcribes ? this.provider : '';
                },

                audioProviderInfo() {
                    return this.providerByKey(this.audioEffective);
                },

                effectiveAudioModel() {
                    const choice = this.audioChoices[this.audioEffective];

                    return choice === this.OTHER ? this.customAudioModels[this.audioEffective] : choice;
                },

                needsAudioKey() {
                    return this.audioEffective
                        && this.audioEffective !== this.provider
                        && ! this.providerByKey(this.audioEffective).hasKey;
                },

                isDirty() {
                    return this.initial !== '' && this.snapshot() !== this.initial;
                },
            },

            watch: {
                provider(provider) {
                    this.showKey = false;

                    this.modelMenuOpen = false;

                    // The text provider cannot also be the separate transcription provider.
                    if (this.audioProvider === provider) {
                        this.audioProvider = '';
                    }

                    // Claude does not transcribe: suggest a provider that already has a key.
                    if (! this.current.transcribes && ! this.audioProvider) {
                        this.audioProvider = this.settings.providers.find((item) => item.transcribes && item.hasKey)?.key ?? '';
                    }
                },
            },

            methods: {
                field(name) {
                    return `general[crm_ai][settings][${name}]`;
                },

                providerByKey(key) {
                    return this.settings.providers.find((item) => item.key === key) ?? this.settings.providers[0];
                },

                toggleModelMenu() {
                    this.modelMenuOpen ? this.closeModelMenu() : this.openModelMenu();
                },

                openModelMenu() {
                    this.activeIndex = Math.max(0, this.modelOptions.findIndex((item) => item.id === this.models[this.provider]));

                    this.modelMenuOpen = true;

                    this.scrollToActive();
                },

                closeModelMenu() {
                    this.modelMenuOpen = false;
                },

                chooseModel(id) {
                    this.models[this.provider] = id;

                    this.closeModelMenu();

                    this.$nextTick(() => (id === this.OTHER ? this.$refs.customModel : this.$refs.modelButton)?.focus());
                },

                /**
                 * Arrow keys move, Enter or Space picks, Escape and Tab close, as in a native select.
                 */
                onModelKeydown(event) {
                    const last = this.modelOptions.length - 1;

                    if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
                        event.preventDefault();

                        if (! this.modelMenuOpen) {
                            this.openModelMenu();

                            return;
                        }

                        const step = event.key === 'ArrowDown' ? 1 : -1;

                        this.activeIndex = Math.min(last, Math.max(0, this.activeIndex + step));

                        this.scrollToActive();

                        return;
                    }

                    if (['Enter', ' '].includes(event.key)) {
                        event.preventDefault();

                        this.modelMenuOpen
                            ? this.chooseModel(this.modelOptions[this.activeIndex].id)
                            : this.openModelMenu();

                        return;
                    }

                    if (['Home', 'End'].includes(event.key) && this.modelMenuOpen) {
                        event.preventDefault();

                        this.activeIndex = event.key === 'Home' ? 0 : last;

                        this.scrollToActive();

                        return;
                    }

                    if (['Escape', 'Tab'].includes(event.key) && this.modelMenuOpen) {
                        if (event.key === 'Escape') {
                            event.preventDefault();
                        }

                        this.closeModelMenu();
                    }
                },

                scrollToActive() {
                    this.$nextTick(() => document.getElementById('crm-ai-model-' + this.activeIndex)?.scrollIntoView({ block: 'nearest' }));
                },

                closeModelMenuOutside(event) {
                    if (this.modelMenuOpen && ! this.$refs.modelSelect?.contains(event.target)) {
                        this.closeModelMenu();
                    }
                },

                /**
                 * "Raciocínio: Muito avançado · Velocidade: Rápido · Custo: Baixo"
                 */
                specs(model) {
                    const { text } = this.settings;

                    return [
                        `${text.reasoning}: ${text.levels.reasoning[model.reasoning]}`,
                        `${text.speed}: ${text.levels.speed[model.speed]}`,
                        `${text.cost}: ${text.levels.cost[model.cost]}`,
                    ].join(' · ');
                },

                snapshot() {
                    return JSON.stringify([
                        this.enabled, this.provider, this.effectiveModel, this.audioProvider,
                        this.audioEffective ? this.effectiveAudioModel : null,
                        this.keys[this.provider] ?? '', this.audioKey,
                    ]);
                },

                /**
                 * Checks the saved settings; the answer comes in the CRM's usual notice.
                 */
                async test() {
                    this.testing = true;

                    try {
                        const { data } = await this.$axios.post(this.settings.testUrl, {}, { headers: { Accept: 'application/json' } });

                        this.$emitter.emit('add-flash', { type: data.ok ? 'success' : 'warning', message: data.message });
                    } catch (error) {
                        this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message ?? this.settings.text.requestError });
                    } finally {
                        this.testing = false;
                    }
                },
            },
        });
    </script>
@endPushOnce
