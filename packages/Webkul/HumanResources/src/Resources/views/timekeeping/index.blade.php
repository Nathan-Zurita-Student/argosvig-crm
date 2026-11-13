<x-admin::layouts>
    <x-slot:title>@lang('human_resources::workforce.timekeeping')</x-slot>

    <div v-pre class="flex flex-col gap-4">
        <section class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
            <h1 class="text-xl font-bold">@lang('human_resources::workforce.timekeeping')</h1>
            <p class="mt-2">@lang('human_resources::workforce.timekeeping_hint')</p>
            @include('human_resources::workforce.errors')

            @if (bouncer()->hasPermission('timekeeping.import'))
                <form method="POST" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3" action="{{ route('admin.human_resources.timekeeping.import') }}">
                    @csrf
                    <label>
                        @lang('human_resources::workforce.csv_file')
                        <input type="file" name="file" accept=".csv" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                    </label>
                    <button type="submit" class="primary-button">@lang('human_resources::workforce.import')</button>
                    <a class="secondary-button" href="{{ route('admin.human_resources.timekeeping.template') }}">@lang('human_resources::workforce.template')</a>
                </form>
                <p class="mt-2 text-sm">@lang('human_resources::workforce.csv_hint')</p>
            @endif
        </section>

        <section class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
            <h2 class="mb-3 font-bold">@lang('human_resources::workforce.marks')</h2>
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <label>
                    @lang('human_resources::app.menu.employees')
                    <select name="employee_id" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                        <option value="">@lang('human_resources::workforce.all')</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) request('employee_id') === (string) $employee->id)>{{ $employee->full_name }} · {{ $employee->employee_number }}</option>
                        @endforeach
                    </select>
                </label>
                <label>@lang('human_resources::workforce.from') <input name="from" type="date" value="{{ $from->toDateString() }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white"></label>
                <label>@lang('human_resources::workforce.to') <input name="to" type="date" value="{{ $to->toDateString() }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white"></label>
                <button class="primary-button" type="submit">@lang('human_resources::workforce.filter')</button>
                <a class="secondary-button" href="{{ route('admin.human_resources.timekeeping.export', request()->only(['employee_id', 'from', 'to'])) }}">@lang('human_resources::workforce.export')</a>
            </form>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr>
                        <th scope="col" class="p-2">@lang('human_resources::app.employees.fields.employee_number')</th>
                        <th scope="col" class="p-2">@lang('human_resources::app.menu.employees')</th>
                        <th scope="col" class="p-2">@lang('human_resources::workforce.occurred_at')</th>
                        <th scope="col" class="p-2">@lang('human_resources::workforce.source')</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr class="border-t border-gray-200 dark:border-gray-800">
                                <td class="p-2">{{ $entry->employee_number }}</td>
                                <td class="p-2">{{ $entry->full_name }}</td>
                                <td class="p-2">{{ \Carbon\CarbonImmutable::parse($entry->occurred_at, 'UTC')->setTimezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</td>
                                <td class="p-2">@lang("human_resources::workforce.sources.{$entry->source}")</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-4 text-center">@lang('human_resources::workforce.no_marks')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('human_resources::workforce.pagination', ['page' => $entries])
        </section>

        <section class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
            <h2 class="mb-3 font-bold">@lang('human_resources::workforce.recent_imports')</h2>
            <ul class="space-y-2">
                @forelse ($batches as $batch)
                    <li>{{ \Illuminate\Support\Carbon::parse($batch->created_at)->format('d/m/Y H:i') }} · @lang("human_resources::workforce.sources.{$batch->source}") · @lang('human_resources::workforce.imported', ['inserted' => $batch->inserted, 'duplicates' => $batch->received - $batch->inserted])</li>
                @empty
                    <li>@lang('human_resources::workforce.no_history')</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-admin::layouts>
