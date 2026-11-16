<x-admin::layouts>
    <x-slot:title>@lang('human_resources::app.incidents.create.title')</x-slot>
    <x-admin::form :action="route('admin.human_resources.incidents.store')" method="POST">
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap justify-between gap-3 rounded-lg border border-gray-300 bg-white p-4 text-gray-800 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                <h1 class="text-xl font-bold dark:text-white">@lang('human_resources::app.incidents.create.title')</h1>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.human_resources.incidents.index') }}" class="secondary-button">@lang('human_resources::app.common.cancel')</a>
                    <button class="primary-button">@lang('human_resources::app.common.save')</button>
                </div>
            </div>
            @if (\Webkul\HumanResources\Http\Controllers\IncidentDraftController::allowed())
                @include('human_resources::ai.incident-draft')
            @endif

            <div class="rounded-lg border border-gray-300 bg-white p-4 text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                @include('human_resources::incidents.form', ['incident' => null])
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
