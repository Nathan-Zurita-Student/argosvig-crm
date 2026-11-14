<x-admin::layouts>
    <x-slot:title>@lang('human_resources::ai.connection')</x-slot>

    <section v-pre class="flex flex-col gap-4 rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold dark:text-white">@lang('human_resources::ai.connection')</h1>
            <a class="secondary-button" href="{{ route('admin.configuration.index', ['slug' => 'general', 'slug2' => 'crm_ai']) }}">@lang('human_resources::ai.settings')</a>
        </div>
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt class="font-medium">@lang('human_resources::ai.provider')</dt><dd>@lang('human_resources::ai.providers.'.$provider)</dd></div>
            <div><dt class="font-medium">@lang('human_resources::ai.key')</dt><dd>@lang($configured ? 'human_resources::ai.configured' : 'human_resources::ai.key_missing')</dd></div>
            <div><dt class="font-medium">@lang('human_resources::ai.availability')</dt><dd>@lang($enabled ? 'human_resources::ai.enabled' : 'human_resources::ai.disabled')</dd></div>
            <div><dt class="font-medium">@lang('human_resources::ai.text_model')</dt><dd>{{ $model }}</dd></div>
            <div><dt class="font-medium">@lang('human_resources::ai.audio_model')</dt><dd>{{ $audioModel ? trans('human_resources::ai.providers.'.$audioProvider).' · '.$audioModel : trans('human_resources::ai.audio_unavailable') }}</dd></div>
        </dl>
        @if (session('ai_status'))
            <p role="status" class="border-t border-gray-200 pt-3 dark:border-gray-800">@lang('human_resources::ai.'.session('ai_status'))</p>
        @endif
        <form method="POST" action="{{ route('admin.ai.connection.test') }}">
            @csrf
            <button class="primary-button" type="submit" @disabled(! $configured)>@lang('human_resources::ai.test')</button>
        </form>
        <p class="text-sm">@lang('human_resources::ai.test_scope')</p>
    </section>
</x-admin::layouts>
