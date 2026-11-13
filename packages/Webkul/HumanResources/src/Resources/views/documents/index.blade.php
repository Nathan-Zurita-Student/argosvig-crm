<x-admin::layouts>
    <x-slot:title>@lang('human_resources::workforce.documents')</x-slot>

    <div v-pre class="flex flex-col gap-4">
        <div class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold dark:text-white">@lang('human_resources::workforce.documents')</h1>
            <div class="flex flex-wrap gap-2">
                @if ($canReviewMissing)
                    <a class="secondary-button" href="{{ route('admin.human_resources.document_center.missing') }}">@lang('human_resources::readiness.missing_documents')</a>
                @endif
                @if ($canShare)
                    <a class="secondary-button" href="{{ route('admin.human_resources.document_center.create_shared') }}">@lang('human_resources::workforce.new_shared')</a>
                @endif
                @if ($canUpload)
                    <a class="primary-button" href="{{ route('admin.human_resources.document_center.create') }}">@lang('human_resources::app.documents.upload')</a>
                @endif
            </div>
        </div>

        <section class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
            @if ($canViewBoth)
                <nav class="mb-4 flex flex-wrap gap-2" aria-label="{{ trans('human_resources::workforce.documents') }}">
                    @foreach (['' => 'all', 'employee' => 'personal_files', 'shared' => 'solidarity_files'] as $category => $label)
                        <a href="{{ route('admin.human_resources.document_center.index', $category ? ['category' => $category] : []) }}"
                            class="{{ request('category', '') === $category ? 'primary-button' : 'secondary-button' }}"
                            @if (request('category', '') === $category) aria-current="page" @endif>
                            @lang("human_resources::workforce.{$label}")
                        </a>
                    @endforeach
                </nav>
            @endif
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                @if (request('category'))
                    <input type="hidden" name="category" value="{{ request('category') }}">
                @endif
                <label>
                    @lang('human_resources::workforce.search')
                    <input type="search" name="search" maxlength="100" value="{{ request('search') }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                </label>
                <label>
                    @lang('human_resources::app.documents.fields.type')
                    <select name="type" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                        <option value="">@lang('human_resources::workforce.all')</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>@lang("human_resources::workforce.types.{$type}")</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    @lang('human_resources::workforce.competence')
                    <input type="month" name="competence" value="{{ request('competence') }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                </label>
                <label>
                    @lang('human_resources::app.documents.fields.expires_at')
                    <select name="expiry" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                        <option value="">@lang('human_resources::workforce.all')</option>
                        @foreach (['expired', 'soon', 'without_date'] as $expiry)
                            <option value="{{ $expiry }}" @selected(request('expiry') === $expiry)>@lang("human_resources::workforce.expiry.{$expiry}")</option>
                        @endforeach
                    </select>
                </label>
                <button class="primary-button" type="submit">@lang('human_resources::workforce.filter')</button>
                <a class="secondary-button" href="{{ route('admin.human_resources.document_center.index', request()->only('category')) }}">@lang('human_resources::workforce.clear')</a>
            </form>
            @include('human_resources::workforce.errors')

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="p-2">@lang('human_resources::app.documents.fields.name')</th>
                            <th scope="col" class="p-2">@lang('human_resources::app.documents.fields.type')</th>
                            <th scope="col" class="p-2">@lang('human_resources::workforce.linked_to')</th>
                            <th scope="col" class="p-2">@lang('human_resources::workforce.competence')</th>
                            <th scope="col" class="p-2">@lang('human_resources::app.documents.fields.expires_at')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($documents as $document)
                            <tr class="border-t border-gray-200 dark:border-gray-800">
                                <td class="p-2"><a class="font-medium text-brandColor hover:underline dark:text-blue-300" href="{{ route('admin.human_resources.document_center.show', $document->id) }}">{{ $document->name }}</a></td>
                                <td class="p-2">@lang("human_resources::workforce.types.{$document->type}")</td>
                                <td class="p-2">
                                    {{ $document->employee?->full_name ?? $document->organizationLinks->pluck('organization.name')->merge($document->contracts->pluck('contract_number'))->join(', ') ?: '—' }}
                                </td>
                                <td class="p-2">{{ $document->competence?->format('m/Y') ?? '—' }}</td>
                                <td class="p-2">
                                    {{ $document->expires_at?->format('d/m/Y') ?? '—' }}
                                    @if ($document->is_expired)
                                        <span class="text-red-700 dark:text-red-300">@lang('human_resources::workforce.expiry.expired')</span>
                                    @elseif ($document->expires_at?->lte(today()->addDays(30)))
                                        <span class="text-amber-700 dark:text-amber-300">@lang('human_resources::workforce.expiry.soon')</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-4 text-center">@lang('human_resources::app.documents.empty')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('human_resources::workforce.pagination', ['page' => $documents])
        </section>
    </div>
</x-admin::layouts>
