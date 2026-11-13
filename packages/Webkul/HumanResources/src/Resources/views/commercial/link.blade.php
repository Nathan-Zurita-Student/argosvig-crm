@php
    $followUp = match (request()->route()?->getName()) {
        'admin.activities.index' => 'admin.activities.follow_up',
        'admin.quotes.index' => 'admin.quotes.follow_up',
        default => 'admin.leads.follow_up',
    };
@endphp

<a class="secondary-button" href="{{ route($followUp) }}">@lang('human_resources::commercial.title')</a>
