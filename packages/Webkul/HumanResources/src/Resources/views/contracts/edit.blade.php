<x-admin::layouts>
    <x-slot:title>@lang('human_resources::app.contracts.edit.title')</x-slot>

    <x-admin::form :action="route('admin.human_resources.contracts.update', $contract->id)" method="PUT">
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap justify-between gap-3 rounded-lg border border-gray-300 bg-white p-4 text-gray-800 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                <h1 class="text-xl font-bold dark:text-white">@lang('human_resources::app.contracts.edit.title')</h1>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.human_resources.contracts.index') }}" class="secondary-button">@lang('human_resources::app.common.cancel')</a>
                    @if (\Webkul\HumanResources\Services\OperationsBriefing::allowed() && bouncer()->hasPermission('human-resources.contracts'))
                        <a href="{{ route('admin.operations.index', ['contract' => $contract->id, 'from' => today()->subWeek()->toDateString(), 'to' => today()->toDateString()]) }}" class="secondary-button">@lang('human_resources::operations.title')</a>
                    @endif
                    @if ($canRenew)
                        <a href="{{ route('admin.human_resources.contracts.create', ['renews_contract_id' => $contract->id]) }}" class="secondary-button">@lang('human_resources::readiness.renew_contract')</a>
                    @endif
                    <button class="primary-button">@lang('human_resources::app.common.save')</button>
                </div>
            </div>
            <div class="rounded-lg border border-gray-300 bg-white p-4 text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                @include('human_resources::workforce.errors')
                @include('human_resources::contracts.form')
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
