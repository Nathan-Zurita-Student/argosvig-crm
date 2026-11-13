<x-admin::layouts>
    <x-slot:title>@lang('human_resources::app.contracts.index.title')</x-slot>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div><p class="text-xl font-bold dark:text-white">@lang('human_resources::app.contracts.index.title')</p><p class="text-sm text-gray-500">@lang('human_resources::app.contracts.index.description')</p></div>
            @if (bouncer()->hasPermission('human-resources.contracts.create'))<a href="{{ route('admin.human_resources.contracts.create') }}" class="primary-button">@lang('human_resources::app.contracts.index.create')</a>@endif
        </div>
        <nav class="flex flex-wrap gap-2" aria-label="{{ trans('human_resources::app.contracts.index.title') }}">
            @foreach (['' => 'human_resources::workforce.all', 'soon' => 'human_resources::readiness.expiring_contracts'] as $expiry => $label)
                <a class="{{ request('expiry', '') === $expiry ? 'primary-button' : 'secondary-button' }}"
                    href="{{ route('admin.human_resources.contracts.index', $expiry ? ['expiry' => $expiry] : []) }}"
                    @if (request('expiry', '') === $expiry) aria-current="page" @endif>
                    @lang($label)
                </a>
            @endforeach
        </nav>
        <x-admin::datagrid :src="route('admin.human_resources.contracts.index', request()->only('expiry'))"><x-admin::shimmer.datagrid /></x-admin::datagrid>
    </div>
</x-admin::layouts>
