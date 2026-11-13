<x-admin::layouts>
    <x-slot:title>@lang('human_resources::operations.incident_draft')</x-slot>

    <section v-pre class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold dark:text-white">@lang('human_resources::operations.incident_draft')</h1>
            <a class="secondary-button" href="{{ route('admin.human_resources.incidents.create') }}">@lang('human_resources::app.common.cancel')</a>
        </div>
        <p class="mt-3">@lang('human_resources::operations.draft_privacy')</p>
        @include('human_resources::workforce.errors')
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.operations.incident_draft.store') }}" class="mt-4 flex flex-col gap-4">
            @csrf
            @if ($audioAvailable)
                <label>
                    @lang('human_resources::operations.audio')
                    <input type="file" name="audio" accept=".wav,.mp3,.m4a,.ogg,.webm,audio/*" capture="microphone" class="mt-1 block w-full text-sm">
                </label>
            @endif
            <label>
                @lang('human_resources::operations.narrative')
                <textarea name="narrative" rows="5" maxlength="10000" @required(! $audioAvailable) class="mt-1 block w-full rounded-md border border-gray-300 bg-white p-2 dark:border-gray-800 dark:bg-gray-950">{{ old('narrative') }}</textarea>
            </label>
            <div><button class="primary-button" type="submit">@lang('human_resources::operations.generate_draft')</button></div>
        </form>
    </section>
</x-admin::layouts>
