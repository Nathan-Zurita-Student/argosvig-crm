<x-admin::layouts>
    <x-slot:title>{{ $incident->code }}</x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gradient-to-r from-[#061534] to-[#163d7a] p-5 text-white">
            <div class="min-w-0">
                <p class="text-xs text-blue-200">{{ $incident->code }}</p>
                <h1 class="mt-1 break-words text-2xl font-bold">{{ $incident->title }}</h1>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.human_resources.incidents.index') }}" class="rounded-lg border border-white/40 px-4 py-2 text-white">@lang('human_resources::app.common.back')</a>
                @if (bouncer()->hasPermission('human-resources.incidents.edit'))
                    <a href="{{ route('admin.human_resources.incidents.edit', $incident->id) }}" class="rounded-lg bg-white px-4 py-2 font-semibold text-[#0a2454]">@lang('human_resources::app.common.edit')</a>
                @endif
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="min-w-0 rounded-lg border border-gray-300 bg-white p-5 text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 lg:col-span-2">
                <h2 class="font-semibold dark:text-white">@lang('human_resources::app.incidents.fields.description')</h2>
                <p class="mt-2 whitespace-pre-line break-words text-sm text-gray-600 dark:text-gray-300">{{ $incident->description }}</p>

                @if ($incident->resolution)
                    <div class="mt-5 border-t border-gray-200 pt-4 dark:border-gray-800">
                        <h2 class="font-semibold dark:text-white">@lang('human_resources::app.incidents.fields.resolution')</h2>
                        <p class="mt-2 whitespace-pre-line break-words text-sm text-gray-600 dark:text-gray-300">{{ $incident->resolution }}</p>
                    </div>
                @endif
            </div>

            <dl class="rounded-lg border border-gray-300 bg-white p-5 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                @foreach ([
                    trans('human_resources::app.incidents.fields.status') => trans("human_resources::app.incidents.status.{$incident->status}"),
                    trans('human_resources::app.incidents.fields.severity') => trans("human_resources::app.incidents.severity.{$incident->severity}"),
                    trans('human_resources::app.incidents.fields.occurred_at') => $incident->occurred_at->format('d/m/Y H:i'),
                    trans('human_resources::app.incidents.fields.location') => $incident->location,
                    trans('human_resources::app.incidents.fields.employee_name') => $incident->employee?->full_name,
                    trans('human_resources::app.incidents.fields.assigned_to') => $incident->assignee?->name,
                ] as $label => $value)
                    <div class="mb-4">
                        <dt class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="mt-1 text-gray-800 dark:text-gray-200">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        @include('human_resources::incidents.images-gallery')
    </div>
</x-admin::layouts>
