{{--
    Next to the file field of a document form: the AI reads the chosen file and fills in the
    type, the name and the expiration date (an ASO or CNV validity, for example).

    Plain JavaScript with delegated listeners, because some document forms are rendered
    inside `v-pre`, where Vue components do not mount.
--}}
@if (
    bouncer()->hasPermission('crm-ai')
    && app(\Webkul\HumanResources\Services\OperationsAi::class)->available()
    && (bouncer()->hasPermission('human-resources.employees.documents') || bouncer()->hasPermission('human-resources.document-center.manage'))
)
    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 md:col-span-2">
        <button
            type="button"
            class="secondary-button"
            data-ai-document-reader
            data-url="{{ route('admin.ai.read_document') }}"
            data-label="✦ @lang('human_resources::ai.document.read')"
            data-reading="@lang('human_resources::ai.document.reading')"
            data-done="@lang('human_resources::ai.document.done')"
            data-no-date="@lang('human_resources::ai.document.no_date')"
            data-error="@lang('human_resources::ai.request_error')"
            disabled
        >
            ✦ @lang('human_resources::ai.document.read')
        </button>

        <p class="text-xs italic leading-5 text-gray-600 dark:text-gray-300">@lang('human_resources::ai.document.read_help')</p>
    </div>

    @pushOnce('scripts')
        <script type="module">
            // Registered once per document: Turbo runs this script again on every page.
            if (! window.aiDocumentReader) {
                window.aiDocumentReader = true;

                const readerOf = (element) => element.closest('form')?.querySelector('[data-ai-document-reader]');

                const setField = (form, name, value) => {
                    const field = form.querySelector(`[name="${name}"]`);

                    if (! field || value === null || value === undefined) {
                        return false;
                    }

                    if (field.tagName === 'SELECT' && ! [...field.options].some((option) => option.value === value)) {
                        return false;
                    }

                    field.value = value;

                    // Lets the form validation of the Vue forms see the new value.
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));

                    return true;
                };

                // The button becomes available once a file is chosen.
                document.addEventListener('change', (event) => {
                    if (event.target.matches?.('input[type="file"][name="file"]')) {
                        const button = readerOf(event.target);

                        if (button) {
                            button.disabled = ! event.target.files.length;
                        }
                    }
                });

                document.addEventListener('click', async (event) => {
                    const button = event.target.closest?.('[data-ai-document-reader]');

                    if (! button || button.disabled) {
                        return;
                    }

                    const form = button.closest('form');
                    const file = form?.querySelector('input[type="file"][name="file"]')?.files?.[0];
                    const types = [...(form?.querySelector('select[name="type"]')?.options ?? [])].map((option) => option.value).filter(Boolean);

                    if (! file || ! types.length) {
                        return;
                    }

                    const payload = new FormData();

                    payload.append('file', file);

                    types.forEach((type) => payload.append('types[]', type));

                    button.disabled = true;
                    button.textContent = button.dataset.reading;

                    try {
                        const { data } = await window.axios.post(button.dataset.url, payload, { headers: { Accept: 'application/json' } });

                        setField(form, 'type', data.type);

                        if (! form.querySelector('[name="name"]')?.value) {
                            setField(form, 'name', data.name);
                        }

                        const dated = setField(form, 'expires_at', data.expires_at);

                        window.emitter?.emit('add-flash', {
                            type: dated ? 'success' : 'warning',
                            message: [dated ? button.dataset.done : button.dataset.noDate, data.note].filter(Boolean).join(' '),
                        });
                    } catch (error) {
                        window.emitter?.emit('add-flash', { type: 'error', message: error.response?.data?.message || button.dataset.error });
                    } finally {
                        button.disabled = false;
                        button.textContent = button.dataset.label;
                    }
                });
            }
        </script>
    @endPushOnce
@endif
