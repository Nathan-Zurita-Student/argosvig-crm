<x-admin::layouts>
    <x-slot:title>@lang('human_resources::operations.find_cover')</x-slot>

    <section v-pre class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold dark:text-white">@lang('human_resources::operations.find_cover') · {{ $site->name }}</h1>
            <a class="secondary-button" href="{{ route('admin.operations.index', ['site' => $site->id]) }}">@lang('human_resources::operations.title')</a>
        </div>
        @if ($gap)
            <p class="mt-3">{{ $gap['from']->format('d/m/Y H:i') }} – {{ $gap['to']->format('d/m/Y H:i') }}</p>
            <p class="mt-2 text-sm">@lang('human_resources::operations.candidate_scope')</p>
            <ul class="mt-3 flex flex-col gap-2">
                @forelse ($candidates as $candidate)
                    <li class="border-t border-gray-200 pt-2 dark:border-gray-800">
                        @if (bouncer()->hasPermission('human-resources.employees.view'))
                            <a class="font-medium text-brandColor hover:underline dark:text-blue-300" href="{{ route('admin.human_resources.employees.show', $candidate->id) }}">{{ $candidate->full_name }}</a>
                        @else
                            <span class="font-medium">{{ $candidate->full_name }}</span>
                        @endif
                        @if ($candidate->job_title) · {{ $candidate->job_title }} @endif
                    </li>
                @empty
                    <li>@lang('human_resources::operations.empty')</li>
                @endforelse
            </ul>
            @if (bouncer()->hasPermission('human-resources.shifts.create'))
                <a class="primary-button mt-4 inline-flex" href="{{ route('admin.human_resources.shifts.create') }}">@lang('human_resources::app.shifts.create.title')</a>
            @endif
        @else
            <p class="mt-3">@lang('human_resources::operations.coverage_updated')</p>
        @endif
    </section>
</x-admin::layouts>
