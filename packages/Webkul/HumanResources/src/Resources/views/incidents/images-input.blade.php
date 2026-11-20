@php
    $imageErrors = collect($errors->get('images.*'))->flatten()
        ->merge($errors->get('images'))->merge($errors->get('image_count'))->unique();
@endphp

<v-incident-image-input></v-incident-image-input>

@pushOnce('scripts')
    <script type="text/x-template" id="v-incident-image-input-template">
        <section aria-labelledby="incident-image-heading" class="grid min-w-0 gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-800 md:col-span-2">
            <h2 id="incident-image-heading" class="text-sm font-semibold text-gray-800 dark:text-white">
                @lang('human_resources::app.incidents.images.title')
            </h2>

            <input type="hidden" name="image_count" :value="images.length">
            <input ref="submission" type="file" name="images[]" multiple hidden>
            <input ref="picker" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden v-on:change="addImages">

            <div class="flex flex-wrap items-center gap-3">
                <button
                    ref="addButton"
                    type="button"
                    class="secondary-button"
                    aria-describedby="incident-image-help{{ $imageErrors->isNotEmpty() ? ' incident-image-errors' : '' }}"
                    v-on:click="$refs.picker.click()"
                >
                    @lang('human_resources::app.incidents.images.add')
                </button>
                <span aria-live="polite" class="text-sm text-gray-600 dark:text-gray-300" v-text="selectionStatus"></span>
            </div>

            <p id="incident-image-help" class="text-sm text-gray-600 dark:text-gray-300">
                @lang('human_resources::app.incidents.images.help', [
                    'count' => \Webkul\HumanResources\Http\Requests\IncidentRequest::imageBatchLimit(),
                    'size' => \Webkul\HumanResources\Http\Requests\IncidentRequest::IMAGE_MAX_KB / 1024,
                ])
            </p>

            @if ($imageErrors->isNotEmpty())
                <ul id="incident-image-errors" role="alert" class="grid gap-1 text-sm text-red-700 dark:text-red-400">
                    @foreach ($imageErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <ul v-if="images.length" ref="previewList" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
                <li v-for="(image, index) in images" :key="image.url" class="min-w-0 rounded-md border border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        class="block w-full rounded-t-md text-start text-brandColor hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brandColor dark:text-blue-300"
                        title="{{ trans('human_resources::app.incidents.images.enlarge') }}"
                        :disabled="image.previewFailed"
                        v-on:click="openPreview(image)"
                    >
                        <img
                            v-if="!image.previewFailed"
                            :src="image.url"
                            alt=""
                            loading="lazy"
                            decoding="async"
                            class="aspect-[4/3] w-full rounded-t-md bg-gray-50 object-contain dark:bg-gray-950"
                            v-on:error="image.previewFailed = true"
                        >
                        <span v-else class="grid aspect-[4/3] place-items-center rounded-t-md bg-gray-50 p-2 text-center text-sm text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                            @lang('human_resources::app.incidents.images.preview_unavailable')
                        </span>
                        <span class="block truncate px-3 py-2 text-sm font-medium" :title="image.file.name" v-text="image.file.name"></span>
                    </button>

                    <button
                        type="button"
                        class="block min-h-10 w-full rounded-b-md border-t border-gray-200 px-3 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brandColor dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                        data-remove-image
                        v-on:click="removeImage(index)"
                    >
                        @lang('human_resources::app.incidents.images.remove')
                        <span class="sr-only" v-text="image.file.name"></span>
                    </button>
                </li>
            </ul>

            <dialog ref="previewDialog" aria-labelledby="incident-preview-heading" class="m-auto w-11/12 max-w-4xl rounded-lg border border-gray-300 bg-white p-4 text-gray-800 backdrop:bg-gray-950/75 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" v-on:close="activeImage = null">
                <div v-if="activeImage" class="grid gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 id="incident-preview-heading" class="min-w-0 break-all text-sm font-semibold" v-text="activeImage.file.name"></h3>
                        <button type="button" class="secondary-button" autofocus v-on:click="$refs.previewDialog.close()">
                            @lang('human_resources::app.incidents.images.close_preview')
                        </button>
                    </div>
                    <img :src="activeImage.url" alt="" class="max-h-[75vh] w-full rounded-md bg-gray-50 object-contain dark:bg-gray-950">
                </div>
            </dialog>
        </section>
    </script>

    <script type="module">
        app.component('v-incident-image-input', {
            template: '#v-incident-image-input-template',

            data() {
                return { images: [], activeImage: null };
            },

            computed: {
                selectionStatus() {
                    if (this.images.length === 0) {
                        return '';
                    }

                    if (this.images.length === 1) {
                        return @js(trans('human_resources::app.incidents.images.selected_one'));
                    }

                    return @js(trans('human_resources::app.incidents.images.selected_many')).replace(':count', this.images.length);
                },
            },

            beforeUnmount() {
                this.images.forEach(({ url }) => URL.revokeObjectURL(url));
            },

            methods: {
                addImages(event) {
                    for (const file of event.target.files) {
                        this.images.push({ file, url: URL.createObjectURL(file), previewFailed: false });
                    }

                    event.target.value = '';
                    this.syncSubmission();
                },

                removeImage(index) {
                    const [removed] = this.images.splice(index, 1);
                    URL.revokeObjectURL(removed.url);
                    this.syncSubmission();

                    this.$nextTick(() => {
                        const removeButtons = this.$refs.previewList?.querySelectorAll('[data-remove-image]');
                        (removeButtons?.[index] ?? removeButtons?.[index - 1] ?? this.$refs.addButton).focus();
                    });
                },

                syncSubmission() {
                    const files = new DataTransfer();
                    for (const { file } of this.images) {
                        files.items.add(file);
                    }
                    this.$refs.submission.files = files.files;
                },

                openPreview(image) {
                    this.activeImage = image;
                    this.$nextTick(() => this.$refs.previewDialog.showModal());
                },
            },
        });
    </script>
@endPushOnce
