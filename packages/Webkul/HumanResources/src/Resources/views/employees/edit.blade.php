<x-admin::layouts>
    <x-slot:title>@lang('human_resources::app.employees.edit.title')</x-slot>

    <x-admin::form :action="route('admin.human_resources.employees.update', $employee->id)" method="PUT">
        <div class="flex flex-col gap-4">
            <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xl font-bold dark:text-white">@lang('human_resources::app.employees.edit.title')</p>
                <div class="flex gap-2">
                    <a href="{{ route('admin.human_resources.employees.index') }}" class="secondary-button">@lang('human_resources::app.common.cancel')</a>
                    <button type="submit" class="primary-button">@lang('human_resources::app.common.save')</button>
                </div>
            </div>

            <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                @include('human_resources::employees.form')
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
