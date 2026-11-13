<x-admin::layouts>
    <x-slot:title>{{ $document->name }}</x-slot>

    <div v-pre class="flex flex-col gap-4">
        <section class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-bold">{{ $document->name }}</h1>
                <div class="flex flex-wrap gap-2">
                    <a class="secondary-button" href="{{ route('admin.human_resources.document_center.index') }}">@lang('human_resources::app.common.back')</a>
                    @if ($canEdit)
                        <a class="secondary-button" href="{{ route('admin.human_resources.document_center.edit', $document->id) }}">@lang('human_resources::app.common.edit')</a>
                    @endif
                    <a class="primary-button" href="{{ route('admin.human_resources.document_center.download', $document->id) }}">@lang('human_resources::app.documents.download')</a>
                </div>
            </div>

            <dl class="grid gap-4 md:grid-cols-3">
                <div><dt class="font-semibold">@lang('human_resources::app.documents.fields.type')</dt><dd>@lang("human_resources::workforce.types.{$document->type}")</dd></div>
                <div><dt class="font-semibold">@lang('human_resources::workforce.competence')</dt><dd>{{ $document->competence?->format('m/Y') ?? '—' }}</dd></div>
                <div><dt class="font-semibold">@lang('human_resources::app.documents.fields.expires_at')</dt><dd>{{ $document->expires_at?->format('d/m/Y') ?? '—' }}</dd></div>
                <div><dt class="font-semibold">@lang('human_resources::app.documents.fields.file')</dt><dd>{{ $document->original_name }}</dd></div>
                <div>
                    <dt class="font-semibold">@lang('human_resources::workforce.linked_to')</dt>
                    @if ($document->employee)
                        <dd>{{ $document->employee->full_name }} · {{ $document->employee->employee_number }}</dd>
                    @else
                        @foreach ($document->organizationLinks as $link)
                            <dd>{{ $link->organization?->name }}</dd>
                        @endforeach
                        @foreach ($document->contracts as $contract)
                            <dd>{{ $contract->contract_number }} · {{ $contract->title }}</dd>
                        @endforeach
                    @endif
                </div>
                <div><dt class="font-semibold">@lang('human_resources::workforce.notes')</dt><dd class="whitespace-pre-line">{{ $document->notes ?? '—' }}</dd></div>
            </dl>

            @if ($canEdit)
                <details class="mt-4">
                    <summary class="cursor-pointer text-red-700 dark:text-red-300">@lang('human_resources::app.common.delete')</summary>
                    <form method="POST" class="mt-2" action="{{ route('admin.human_resources.document_center.delete', $document->id) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="secondary-button">@lang('human_resources::workforce.confirm_delete')</button>
                    </form>
                </details>
            @endif
        </section>

        <section class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
            <h2 class="mb-3 font-bold">@lang('human_resources::workforce.history')</h2>
            <ul class="space-y-2">
                @forelse ($history as $event)
                    <li>{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('d/m/Y H:i') }} · @lang("human_resources::workforce.actions.{$event->action}") · {{ $event->name ?? '—' }}</li>
                @empty
                    <li>@lang('human_resources::workforce.no_history')</li>
                @endforelse
            </ul>
            @include('human_resources::workforce.pagination', ['page' => $history])
        </section>
    </div>
</x-admin::layouts>
