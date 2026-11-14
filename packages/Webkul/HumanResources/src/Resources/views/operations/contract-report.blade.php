<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@lang('human_resources::operations.export_contract')</title>
    <style>
        body { color: #132746; font: 12px sans-serif; margin: 30px; }
        h1 { border-bottom: 2px solid #163d7a; font-size: 22px; padding-bottom: 10px; }
        h2 { font-size: 15px; margin-top: 22px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #d4dfef; padding: 8px; text-align: left; }
    </style>
</head>
<body>
    <h1>@lang('human_resources::operations.contract_report')</h1>
    <p><strong>{{ $contextContract->contract_number }}</strong> · {{ $contextContract->title }}</p>
    <p>{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</p>

    @if ($incidents)
        <h2>@lang('human_resources::operations.incidents') ({{ $incidents->total() }})</h2>
        <table>
            <thead><tr><th>@lang('human_resources::app.incidents.fields.code')</th><th>@lang('human_resources::app.incidents.fields.occurred_at')</th><th>@lang('human_resources::app.incidents.fields.severity')</th><th>@lang('human_resources::app.incidents.fields.status')</th></tr></thead>
            <tbody>
                @foreach ($incidents as $incident)
                    <tr><td>{{ $incident->code }}</td><td>{{ $incident->occurred_at->format('d/m/Y H:i') }}</td><td>@lang('human_resources::app.incidents.severity.'.$incident->severity)</td><td>@lang('human_resources::app.incidents.status.'.$incident->status)</td></tr>
                @endforeach
            </tbody>
        </table>
        @if ($incidents->total() > $incidents->count())
            <p>{{ trans('human_resources::operations.partial', ['total' => $incidents->total()]) }}</p>
        @endif
    @endif

    @if ($shifts)
        <h2>@lang('human_resources::operations.shifts') ({{ $shifts->total() }})</h2>
        <table>
            <thead><tr><th>@lang('human_resources::operations.site')</th><th>@lang('human_resources::app.shifts.fields.starts_at')</th><th>@lang('human_resources::app.shifts.fields.ends_at')</th><th>@lang('human_resources::app.shifts.fields.status')</th></tr></thead>
            <tbody>
                @foreach ($shifts as $shift)
                    <tr><td>{{ $shift->workSite?->name }}</td><td>{{ $shift->starts_at->format('d/m/Y H:i') }}</td><td>{{ $shift->ends_at->format('d/m/Y H:i') }}</td><td>@lang('human_resources::app.shifts.status.'.$shift->status)</td></tr>
                @endforeach
            </tbody>
        </table>
        @if ($shifts->total() > $shifts->count())
            <p>{{ trans('human_resources::operations.partial', ['total' => $shifts->total()]) }}</p>
        @endif
    @endif
</body>
</html>
