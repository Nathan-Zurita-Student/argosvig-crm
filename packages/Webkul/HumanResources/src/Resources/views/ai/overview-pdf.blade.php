@extends('human_resources::ai.pdf-layout')

@section('document')
    @if (! $only)
        <p class="standfirst">@lang('human_resources::reports.intro')</p>
        <p class="meta">{{ $issued }} &nbsp; · &nbsp; {{ number_format(count($sections), 0, ',', '.') }} @lang('human_resources::reports.sections_available')</p>

        <h2>@lang('human_resources::reports.summary')</h2>
        @if ($sections)
            <table class="index">
                <thead>
                    <tr>
                        <th class="group-name">@lang('human_resources::reports.group')</th>
                        <th>@lang('human_resources::reports.section')</th>
                        <th class="numeric">@lang('human_resources::reports.records')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sections as $section)
                        <tr>
                            <td>{{ $section['group'] }}</td>
                            <td>
                                @if ($section['total'])
                                    <a href="#section-{{ $section['key'] }}">{{ $section['title'] }}</a>
                                @else
                                    {{ $section['title'] }}
                                @endif
                            </td>
                            <td class="numeric">{{ number_format($section['total'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>@lang('human_resources::reports.no_sections')</p>
        @endif

        <p class="note">@lang('human_resources::reports.scope')</p>
    @endif

    @foreach (collect($sections)->filter(fn ($section) => $section['total'] || $only)->groupBy('group') as $group => $entries)
        <div @class(['chapter' => ! $only, 'chapter-first' => ! $only && $loop->first])>
            @unless ($only)
                <h2>{{ $group }}</h2>
            @endunless

            @foreach ($entries as $section)
                <div class="keep" id="section-{{ $section['key'] }}">
                    @unless ($only)
                        <h3>{{ $section['title'] }}</h3>
                    @endunless
                    <p class="meta">
                        @if ($section['rows'])
                            @lang('human_resources::reports.range', [
                                'from' => number_format($section['from'], 0, ',', '.'),
                                'to' => number_format($section['to'], 0, ',', '.'),
                                'total' => number_format($section['total'], 0, ',', '.'),
                            ])
                        @elseif ($section['total'])
                            @lang('human_resources::reports.deferred', ['total' => number_format($section['total'], 0, ',', '.')])
                        @else
                            @lang('human_resources::reports.no_records')
                        @endif
                    </p>
                </div>

                @if ($section['rows'])
                    <table class="records">
                        <thead>
                            <tr>
                                @foreach ($section['columns'] as $column => $label)
                                    <th @if (in_array($column, ['title', 'name', 'subject', 'full_name', 'employee'], true)) style="width: {{ count($section['columns']) <= 4 ? '42%' : '31%' }}" @endif>
                                        {{ $label }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section['rows'] as $row)
                                <tr>
                                    @foreach ($row as $value)
                                        <td>{{ filled($value) ? $value : '—' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if ($section['next'])
                    <p class="note">
                        <a href="{{ route('admin.ai.report', ['section' => $section['key'], 'page' => $section['next']]) }}">
                            @lang($section['next'] === 1 ? 'human_resources::reports.first_volume' : 'human_resources::reports.next_volume')
                        </a>
                    </p>
                @endif
            @endforeach
        </div>
    @endforeach
@endsection
