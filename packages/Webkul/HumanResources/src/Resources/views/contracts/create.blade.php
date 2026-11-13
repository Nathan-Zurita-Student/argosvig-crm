<x-admin::layouts>
    <x-slot:title>@lang('human_resources::app.contracts.create.title')</x-slot>

    <x-admin::form :action="route('admin.human_resources.contracts.store')" method="POST">
        <div class="flex flex-col gap-4">
            <div class="flex justify-between rounded-lg border border-gray-300 bg-white p-4 text-gray-800 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                <h1 class="text-xl font-bold dark:text-white">@lang('human_resources::app.contracts.create.title')</h1>
                <div class="flex gap-2">
                    <a href="{{ route('admin.human_resources.contracts.index') }}" class="secondary-button">@lang('human_resources::app.common.cancel')</a>
                    <button class="primary-button">@lang('human_resources::app.common.save')</button>
                </div>
            </div>
            <div class="rounded-lg border border-gray-300 bg-white p-4 text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                @if ($quoteVersion)
                    <input type="hidden" name="quote_version_id" value="{{ $quoteVersion->id }}">
                    <p v-pre class="mb-4 font-medium">{{ trans('human_resources::commercial.quote.contract_source', ['subject' => $quoteVersion->snapshot['subject'], 'number' => $quoteVersion->number]) }}</p>
                @endif
                @include('human_resources::workforce.errors')
                @include('human_resources::contracts.form')
            </div>
        </div>
    </x-admin::form>
</x-admin::layouts>
