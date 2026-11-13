<x-admin::layouts>
    <x-slot:title>@lang('human_resources::app.shifts.create.title')</x-slot>
    <x-admin::form :action="route('admin.human_resources.shifts.store', $suggestion ? ['coverage' => 1] : [])" method="POST">
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap justify-between gap-3 rounded-lg border border-gray-300 bg-white p-4 text-gray-800 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                <h1 class="text-xl font-bold dark:text-white">@lang('human_resources::app.shifts.create.title')</h1>
                <div class="flex gap-2">
                    <a href="{{ route('admin.human_resources.shifts.index') }}" class="secondary-button">@lang('human_resources::app.common.cancel')</a>
                    <button class="primary-button">@lang('human_resources::app.common.save')</button>
                </div>
            </div>
            <div class="rounded-lg border border-gray-300 bg-white p-4 text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                @if ($suggestion)
                    <p class="mb-3">@lang('human_resources::operations.candidate_scope')</p>
                @endif
                @include('human_resources::workforce.errors')
                @include('human_resources::shifts.form', ['shift' => $suggestion])
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
