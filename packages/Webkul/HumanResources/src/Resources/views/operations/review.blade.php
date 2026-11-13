<div class="grid gap-4 lg:grid-cols-2">
    @if ($punches)
        <section v-pre class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
            <h2 class="font-semibold">@lang('human_resources::operations.punch_title')</h2>
            <p class="mt-1 text-sm">@lang('human_resources::operations.punch_scope')</p>
            <ul class="mt-3 flex flex-col gap-3">
                @forelse ($punches as ['shift' => $shift, 'result' => $result])
                    <li class="border-t border-gray-200 pt-3 dark:border-gray-800">
                        <a class="font-medium text-brandColor hover:underline dark:text-blue-300" href="{{ route('admin.human_resources.timekeeping.index', ['employee_id' => $shift->employee_id, 'from' => $shift->starts_at->copy()->subMinutes(30)->toDateString(), 'to' => $shift->ends_at->copy()->addMinutes(30)->toDateString()]) }}">
                            {{ trans('human_resources::operations.shift_reference', ['id' => $shift->id]) }} · {{ $shift->workSite?->name ?? '—' }}
                        </a>
                        <p class="text-sm">{{ $shift->ends_at->format('d/m/Y H:i') }} · @lang('human_resources::operations.punch.'.$result)</p>
                    </li>
                @empty
                    <li>@lang('human_resources::operations.empty')</li>
                @endforelse
            </ul>
            @include('human_resources::workforce.pagination', ['page' => $punches])
        </section>
    @endif

    @if ($documents || $contracts)
        <section v-pre class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
            <h2 class="font-semibold">@lang('human_resources::operations.deadlines')</h2>
            @if ($documents)
                <h3 class="mt-3 font-medium">@lang('human_resources::workforce.documents')</h3>
                @if ($site)
                    <p class="text-sm">@lang('human_resources::operations.contract_documents')</p>
                @endif
                <ul class="mt-2 flex flex-col gap-2">
                    @forelse ($documents as $document)
                        <li><a class="text-brandColor hover:underline dark:text-blue-300" href="{{ route('admin.human_resources.document_center.show', $document->id) }}">{{ trans('human_resources::workforce.types.'.$document->type) }}</a> · {{ $document->expires_at->format('d/m/Y') }}</li>
                    @empty
                        <li>@lang('human_resources::operations.empty')</li>
                    @endforelse
                </ul>
                @include('human_resources::workforce.pagination', ['page' => $documents])
            @endif
            @if ($contracts)
                <h3 class="mt-3 font-medium">@lang('human_resources::app.menu.contracts')</h3>
                <ul class="mt-2 flex flex-col gap-2">
                    @forelse ($contracts as $contract)
                        <li>
                            <a class="text-brandColor hover:underline dark:text-blue-300" href="{{ bouncer()->hasPermission('human-resources.contracts.edit') ? route('admin.human_resources.contracts.edit', $contract->id) : route('admin.human_resources.contracts.index', ['expiry' => 'soon']) }}">{{ $contract->contract_number }}</a> · {{ $contract->ends_at->format('d/m/Y') }}
                        </li>
                    @empty
                        <li>@lang('human_resources::operations.empty')</li>
                    @endforelse
                </ul>
                @include('human_resources::workforce.pagination', ['page' => $contracts])
            @endif
        </section>
    @endif
</div>
