<x-admin::layouts>
    <x-slot:title>@lang('human_resources::workforce.documents')</x-slot>

    <section v-pre class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold">{{ $document ? trans('human_resources::app.common.edit') : trans($shared ? 'human_resources::workforce.new_shared' : 'human_resources::app.documents.upload') }}</h1>
            <a class="secondary-button" href="{{ route('admin.human_resources.document_center.index') }}">@lang('human_resources::app.common.back')</a>
        </div>
        @include('human_resources::workforce.errors')
        <form method="POST" enctype="multipart/form-data" action="{{ $document ? route('admin.human_resources.document_center.update', $document->id) : route($shared ? 'admin.human_resources.document_center.store_shared' : 'admin.human_resources.document_center.store') }}">
            @csrf
            @if ($document)
                @method('PUT')
            @endif
            <div class="grid gap-4 md:grid-cols-2">
                @if (! $shared && ! $document)
                    <label>
                        @lang('human_resources::app.menu.employees')
                        <select name="employee_id" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                            <option value="">@lang('human_resources::app.common.select')</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->full_name }} · {{ $employee->employee_number }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label>
                    @lang('human_resources::app.documents.fields.type')
                    <select name="type" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(old('type', $document?->type) === $type)>@lang("human_resources::workforce.types.{$type}")</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    @lang('human_resources::app.documents.fields.name')
                    <input name="name" maxlength="255" value="{{ old('name', $document?->name) }}" @required($shared) class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                </label>
                @if ($shared)
                    <p class="md:col-span-2">@lang('human_resources::workforce.shared_hint')</p>
                    <label>
                        @lang('human_resources::workforce.organizations')
                        <select name="organizations[]" multiple size="5" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                            @foreach ($organizations as $organization)
                                <option value="{{ $organization->id }}" @selected(in_array($organization->id, (array) old('organizations', $document?->organizationLinks->pluck('organization_id')->all() ?? [])))>{{ $organization->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        @lang('human_resources::app.menu.contracts')
                        <select name="contracts[]" multiple size="5" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                            @foreach ($contracts as $contract)
                                <option value="{{ $contract->id }}" @selected(in_array($contract->id, (array) old('contracts', $document?->contracts->modelKeys() ?? [])))>{{ $contract->contract_number }} · {{ $contract->title }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <label>
                        @lang('human_resources::workforce.competence')
                        <input type="month" name="competence" value="{{ old('competence', $document?->competence?->format('Y-m')) }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                        <small>@lang('human_resources::workforce.competence_hint')</small>
                    </label>
                @endif
                <label>
                    @lang('human_resources::app.documents.fields.expires_at')
                    <input type="date" name="expires_at" value="{{ old('expires_at', $document?->expires_at?->toDateString()) }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                </label>
                <label>
                    @lang('human_resources::app.documents.fields.file')
                    <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" @required(! $document) class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                    <small>@lang('human_resources::workforce.file_hint')</small>
                </label>
                <label class="md:col-span-2">
                    @lang('human_resources::workforce.notes')
                    <textarea name="notes" maxlength="2000" rows="3" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">{{ old('notes', $document?->notes) }}</textarea>
                </label>
            </div>
            <button class="primary-button mt-4" type="submit">@lang('human_resources::app.common.save')</button>
        </form>
    </section>
</x-admin::layouts>
