<x-admin::layouts>
    <x-slot:title>@lang('human_resources::commercial.quote.title')</x-slot>

    <section v-pre class="flex flex-col gap-4 rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold dark:text-white">{{ $quote->subject }}</h1>
                <p>@lang('human_resources::commercial.quote.description')</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a class="secondary-button" href="{{ route('admin.quotes.edit', $quote->id) }}">@lang('human_resources::commercial.quote.edit')</a>
                <a class="secondary-button" href="{{ route('admin.quotes.follow_up') }}">@lang('human_resources::commercial.title')</a>
            </div>
        </div>
        @include('human_resources::workforce.errors')

        @if ($current?->status !== 'accepted')
            <form method="POST" action="{{ route('admin.quotes.follow_up.store', $quote->id) }}" class="flex flex-wrap gap-2">
                @csrf
                <input type="hidden" name="version" value="{{ $current?->number ?? 0 }}">
                <button class="secondary-button" name="status" value="sent">@lang('human_resources::commercial.quote.sent')</button>
                @if ($current?->status === 'sent')
                    <button class="primary-button" name="status" value="accepted">@lang('human_resources::commercial.quote.accepted')</button>
                    <button class="secondary-button" name="status" value="declined">@lang('human_resources::commercial.quote.declined')</button>
                @endif
            </form>
        @elseif ($current->contract)
            @if (bouncer()->hasPermission('human-resources.contracts.edit') && ! $current->contract->trashed())
                <div><a class="secondary-button" href="{{ route('admin.human_resources.contracts.edit', $current->contract->id) }}">@lang('human_resources::commercial.quote.open_contract')</a></div>
            @endif
        @elseif (bouncer()->hasPermission('human-resources.contracts.create'))
            <div><a class="primary-button" href="{{ route('admin.human_resources.contracts.create', ['quote_version_id' => $current->id]) }}">@lang('human_resources::commercial.quote.create_contract')</a></div>
        @endif

        @forelse ($versions as $version)
            <details class="rounded-lg border border-gray-300 p-4 dark:border-gray-800">
                <summary class="cursor-pointer font-semibold">
                    @lang('human_resources::commercial.quote.version', ['number' => $version->number]) · @lang('human_resources::commercial.quote.statuses.'.$version->status) · {{ $version->sent_at->format('d/m/Y H:i') }}
                </summary>
                <p class="mt-2">{{ $version->snapshot['subject'] }} · {{ core()->formatBasePrice($version->snapshot['grand_total'] ?? 0) }}</p>
                @if ($version->resolved_at)
                    <p>{{ $version->resolved_at->format('d/m/Y H:i') }}</p>
                @endif
                @foreach ($version->snapshot['items'] as $item)
                    <p>{{ $item['quantity'] }} × {{ $item['name'] ?: $item['sku'] }} · {{ core()->formatBasePrice($item['total']) }}</p>
                @endforeach
            </details>
        @empty
            <p>@lang('human_resources::commercial.quote.no_versions')</p>
        @endforelse
        @include('human_resources::workforce.pagination', ['page' => $versions])
    </section>
</x-admin::layouts>
