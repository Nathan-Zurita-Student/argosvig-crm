<x-admin::layouts>
    <x-slot:title>@lang('human_resources::app.vacations.index.title')</x-slot>

    <div class="flex flex-col gap-4">
        <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <p class="text-xl font-bold text-gray-800 dark:text-white">@lang('human_resources::app.vacations.index.title')</p>
                <p class="text-sm text-gray-500">@lang('human_resources::app.vacations.index.description')</p>
            </div>

            @if (bouncer()->hasPermission('human-resources.vacations.create'))
                <a href="{{ route('admin.human_resources.vacations.create') }}" class="primary-button">
                    @lang('human_resources::app.vacations.index.create')
                </a>
            @endif
        </div>

        <x-admin::datagrid :src="route('admin.human_resources.vacations.index')">
            <x-admin::shimmer.datagrid />
        </x-admin::datagrid>
    </div>
</x-admin::layouts>
