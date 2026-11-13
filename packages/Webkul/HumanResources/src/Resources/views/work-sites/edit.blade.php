<x-admin::layouts>
    <x-slot:title>@lang('human_resources::app.work_sites.edit.title')</x-slot>
    <x-admin::form :action="route('admin.human_resources.work_sites.update', $workSite->id)" method="PUT">
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap justify-between gap-3 rounded-lg border border-gray-300 bg-white p-4 text-gray-800 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                <h1 class="text-xl font-bold dark:text-white">@lang('human_resources::app.work_sites.edit.title')</h1>
                <div class="flex flex-wrap gap-2">
                    @if ($workSite->status === 'active' && \Webkul\HumanResources\Services\OperationsBriefing::allowed() && bouncer()->hasPermission('human-resources.work-sites'))
                        <a href="{{ route('admin.operations.index', ['site' => $workSite->id]) }}" class="secondary-button">@lang('human_resources::operations.title')</a>
                    @endif
                    <a href="{{ route('admin.human_resources.work_sites.index') }}" class="secondary-button">@lang('human_resources::app.common.cancel')</a>
                    <button class="primary-button">@lang('human_resources::app.common.save')</button>
                </div>
            </div>
            <div class="rounded-lg border border-gray-300 bg-white p-4 text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                @include('human_resources::work-sites.form')
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
