<x-admin::layouts>
    <x-slot:title>@lang('human_resources::app.employees.index.title')</x-slot>

    <div class="flex flex-col gap-4">
        <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-300 bg-white px-4 py-2 shadow-sm dark:border-gray-800 dark:bg-gray-900 max-sm:static">
            <div>
                <h1 class="text-xl font-bold dark:text-white">@lang('human_resources::app.employees.index.title')</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300">@lang('human_resources::app.employees.index.description')</p>
            </div>

            @if (bouncer()->hasPermission('human-resources.employees.create'))
                <a href="{{ route('admin.human_resources.employees.create') }}" class="primary-button">
                    @lang('human_resources::app.employees.index.create')
                </a>
            @endif
        </div>

        <x-admin::datagrid :src="route('admin.human_resources.employees.index')">
            <x-admin::shimmer.datagrid />
        </x-admin::datagrid>
    </div>
</x-admin::layouts>
