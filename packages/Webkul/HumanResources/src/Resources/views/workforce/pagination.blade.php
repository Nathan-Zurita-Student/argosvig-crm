@if ($page->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-2" aria-label="{{ trans('human_resources::workforce.pagination') }}">
        <div>@if ($page->previousPageUrl())<a class="secondary-button" href="{{ $page->previousPageUrl() }}">@lang('human_resources::workforce.previous')</a>@endif</div>
        <span>{{ $page->firstItem() }}–{{ $page->lastItem() }} / {{ $page->total() }}</span>
        <div>@if ($page->nextPageUrl())<a class="secondary-button" href="{{ $page->nextPageUrl() }}">@lang('human_resources::workforce.next')</a>@endif</div>
    </nav>
@endif
