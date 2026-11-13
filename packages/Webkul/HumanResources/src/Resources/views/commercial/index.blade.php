<x-admin::layouts>
    <x-slot:title>@lang('human_resources::commercial.title')</x-slot>

    <section v-pre class="flex flex-col gap-4 rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
        <h1 class="text-xl font-bold dark:text-white">@lang('human_resources::commercial.title')</h1>

        <nav class="flex flex-wrap gap-2" aria-label="{{ trans('human_resources::commercial.title') }}">
            @foreach ($tabs as $filter)
                <a class="{{ $tab === $filter ? 'primary-button' : 'secondary-button' }}"
                    href="{{ route('admin.'.$filter.'.follow_up') }}"
                    @if ($tab === $filter) aria-current="page" @endif>
                    @lang("human_resources::commercial.tabs.{$filter}")
                </a>
            @endforeach
        </nav>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th scope="col" class="p-2">@lang('human_resources::commercial.record')</th>
                        <th scope="col" class="p-2">@lang('human_resources::commercial.owner')</th>
                        <th scope="col" class="p-2">@lang("human_resources::commercial.details.{$tab}")</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @php
                            $label = $tab === 'quotes' ? $record->snapshot['subject'] : $record->title;
                            $owner = $tab === 'quotes' ? $record->quote?->user : $record->user;
                            $detail = match ($tab) {
                                'leads' => $record->stage?->name ?? '—',
                                'activities' => $record->schedule_to->format('d/m/Y H:i'),
                                'quotes' => $record->sent_at->format('d/m/Y H:i'),
                            };
                        @endphp
                        <tr class="border-t border-gray-200 dark:border-gray-800">
                            <td class="p-2">
                                @if ($detailRoute)
                                    <a class="font-medium text-brandColor hover:underline dark:text-blue-300" href="{{ route($detailRoute, $tab === 'quotes' ? $record->quote_id : $record->id) }}">{{ $label }}</a>
                                @else
                                    {{ $label }}
                                @endif
                            </td>
                            <td class="p-2">{{ $owner?->name ?? '—' }}</td>
                            <td class="p-2">{{ $detail }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-4 text-center">@lang('human_resources::commercial.empty')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('human_resources::workforce.pagination', ['page' => $records])
    </section>
</x-admin::layouts>
