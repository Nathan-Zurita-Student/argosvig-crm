<x-admin::layouts>
    <x-slot:title>@lang('human_resources::operations.title')</x-slot>

    <div class="flex flex-col gap-4">
        <section v-pre class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
            <h1 class="text-xl font-bold dark:text-white">@lang('human_resources::operations.title')</h1>
            <form method="GET" action="{{ route('admin.operations.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @if ($sites->isNotEmpty())
                    <label>
                        @lang('human_resources::operations.site')
                        <select name="site" class="block rounded-md border border-gray-300 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-950">
                            <option value="">@lang('human_resources::workforce.all')</option>
                            @foreach ($sites as $option)
                                <option value="{{ $option->id }}" @selected($site?->id === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                @foreach (['from' => $from, 'to' => $to] as $field => $date)
                    <label>
                        @lang('human_resources::operations.'.$field)
                        <input type="date" name="{{ $field }}" value="{{ $date }}" class="block rounded-md border border-gray-300 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-950">
                    </label>
                @endforeach
                <button class="primary-button" type="submit">@lang('human_resources::workforce.filter')</button>
            </form>
            @include('human_resources::workforce.errors')
        </section>

        @if ($canAsk)
            <section v-pre class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                <h2 class="font-semibold">@lang('human_resources::operations.ai_title')</h2>
                <form method="POST" action="{{ route('admin.operations.ask') }}" class="mt-3 flex flex-col gap-3">
                    @csrf
                    <input type="hidden" name="from" value="{{ $from }}">
                    <input type="hidden" name="to" value="{{ $to }}">
                    <input type="hidden" name="site" value="{{ $site?->id }}">
                    <label for="question">@lang('human_resources::operations.question')</label>
                    <textarea id="question" name="question" rows="2" maxlength="500" required class="rounded-md border border-gray-300 bg-white p-2 dark:border-gray-800 dark:bg-gray-950">{{ $question ?? old('question', trans('human_resources::operations.default_question')) }}</textarea>
                    <div><button class="primary-button" type="submit">@lang('human_resources::operations.ask')</button></div>
                </form>
                @isset($assistantError)
                    <p role="alert" class="mt-3 text-sm text-red-700 dark:text-red-300">{{ $assistantError }}</p>
                @endisset
                @isset($answer)
                    <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-800">
                        <h3 class="font-medium">@lang('human_resources::operations.answer')</h3>
                        <p class="mt-2 whitespace-pre-wrap">{{ $answer }}</p>
                    </div>
                @endisset
            </section>
        @endif

        @if ($canSeeCoverage)
            <section v-pre class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                <h2 class="font-semibold">@lang('human_resources::operations.coverage_title')</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">@lang('human_resources::operations.coverage_scope')</p>
                <ul class="mt-3 flex flex-col gap-3">
                    @forelse ($gaps as $gap)
                        <li class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-3 dark:border-gray-800">
                            <div>
                                <p class="font-medium">{{ $gap['site']->name }} · {{ trans('human_resources::operations.coverage_count', ['confirmed' => $gap['confirmed'], 'required' => $gap['required']]) }}</p>
                                <p class="text-sm">{{ $gap['from']->format('d/m/Y H:i') }} – {{ $gap['to']->format('d/m/Y H:i') }}</p>
                            </div>
                            @if (\Webkul\HumanResources\Services\ShiftCoverage::canSuggest())
                                <a class="secondary-button" href="{{ route('admin.operations.coverage', ['site' => $gap['site']->id, 'start' => $gap['from']->toDateTimeString(), 'end' => $gap['to']->toDateTimeString()]) }}">
                                    @lang('human_resources::operations.find_cover')
                                </a>
                            @endif
                        </li>
                    @empty
                        <li>@lang('human_resources::operations.empty')</li>
                    @endforelse
                </ul>
                @if ($gapCount > count($gaps))
                    <p class="mt-3 text-sm">{{ trans('human_resources::operations.partial', ['total' => $gapCount]) }}</p>
                @endif
            </section>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            @if ($incidents)
                <section v-pre class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                    <h2 class="font-semibold">@lang('human_resources::operations.incidents')</h2>
                    @if ($site)
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">@lang('human_resources::operations.contract_incidents')</p>
                    @endif
                    <ul class="mt-3 flex flex-col gap-3">
                        @forelse ($incidents as $incident)
                            <li class="border-t border-gray-200 pt-3 dark:border-gray-800">
                                <a class="font-medium text-brandColor hover:underline dark:text-blue-300" href="{{ route('admin.human_resources.incidents.show', $incident->id) }}">{{ $incident->code }} · {{ $incident->title }}</a>
                                <p class="text-sm">{{ $incident->occurred_at->format('d/m/Y H:i') }} · @lang('human_resources::app.incidents.severity.'.$incident->severity) · @lang('human_resources::app.incidents.status.'.$incident->status)</p>
                            </li>
                        @empty
                            <li>@lang('human_resources::operations.empty')</li>
                        @endforelse
                    </ul>
                    @include('human_resources::workforce.pagination', ['page' => $incidents])
                </section>
            @endif

            @if ($shifts)
                <section v-pre class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                    <h2 class="font-semibold">@lang('human_resources::operations.shifts')</h2>
                    <ul class="mt-3 flex flex-col gap-3">
                        @forelse ($shifts as $shift)
                            <li class="border-t border-gray-200 pt-3 dark:border-gray-800">
                                @if (bouncer()->hasPermission('human-resources.shifts.edit'))
                                    <a class="font-medium text-brandColor hover:underline dark:text-blue-300" href="{{ route('admin.human_resources.shifts.edit', $shift->id) }}">{{ $shift->workSite?->name ?? '—' }}</a>
                                @else
                                    <span class="font-medium">{{ $shift->workSite?->name ?? '—' }}</span>
                                @endif
                                <p class="text-sm">{{ trans('human_resources::operations.shift_reference', ['id' => $shift->id]) }} · {{ $shift->starts_at->format('d/m/Y H:i') }} – {{ $shift->ends_at->format('d/m/Y H:i') }} · @lang('human_resources::app.shifts.status.'.$shift->status)</p>
                            </li>
                        @empty
                            <li>@lang('human_resources::operations.empty')</li>
                        @endforelse
                    </ul>
                    @include('human_resources::workforce.pagination', ['page' => $shifts])
                </section>
            @endif
        </div>
    </div>
</x-admin::layouts>
