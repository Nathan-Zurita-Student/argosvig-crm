<x-admin::layouts>
    <x-slot:title>@lang('human_resources::readiness.missing_documents')</x-slot>

    <section v-pre class="flex flex-col gap-4 rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold dark:text-white">@lang('human_resources::readiness.missing_documents')</h1>
                <p>@lang('human_resources::readiness.missing_description')</p>
            </div>
            <a class="secondary-button" href="{{ route('admin.human_resources.document_center.index') }}">@lang('human_resources::workforce.documents')</a>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3">
            <label>
                @lang('human_resources::app.documents.fields.type')
                <select name="type" required class="block rounded-md border border-gray-300 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-950">
                    <option value=""></option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>@lang("human_resources::workforce.types.{$type}")</option>
                    @endforeach
                </select>
            </label>
            <label>
                @lang('human_resources::workforce.competence')
                <input type="month" name="competence" value="{{ request('competence') }}" class="block rounded-md border border-gray-300 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-950">
            </label>
            <button class="primary-button" type="submit">@lang('human_resources::workforce.filter')</button>
        </form>
        @include('human_resources::workforce.errors')

        @if ($employees)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr><th scope="col" class="p-2">@lang('human_resources::app.employees.fields.employee_number')</th><th scope="col" class="p-2">@lang('human_resources::app.employees.fields.full_name')</th></tr></thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr class="border-t border-gray-200 dark:border-gray-800">
                                <td class="p-2">{{ $employee->employee_number }}</td>
                                <td class="p-2">
                                    @if ($canOpenEmployees)
                                        <a class="font-medium text-brandColor hover:underline dark:text-blue-300" href="{{ route('admin.human_resources.employees.show', $employee->id) }}">{{ $employee->full_name }}</a>
                                    @else
                                        {{ $employee->full_name }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="p-4 text-center">@lang('human_resources::readiness.no_missing_documents')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('human_resources::workforce.pagination', ['page' => $employees])
        @endif
    </section>
</x-admin::layouts>
