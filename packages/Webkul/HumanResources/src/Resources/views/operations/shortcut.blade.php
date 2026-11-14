{{-- On small screens only the icon is shown, so the header still fits the logo and the other actions. --}}
<a
    href="{{ route('admin.operations.index') }}"
    @class([
        'secondary-button whitespace-nowrap max-sm:!px-2',
        'max-sm:hidden' => bouncer()->hasPermission('crm-ai') && app(\Webkul\HumanResources\Services\OperationsAi::class)->available(),
    ])
    title="@lang('human_resources::operations.shortcut')"
    aria-label="@lang('human_resources::operations.shortcut')"
    @if (request()->routeIs('admin.operations.*')) aria-current="page" @endif
>
    <span class="icon-activity text-xl sm:hidden"></span>

    <span class="max-sm:hidden">@lang('human_resources::operations.shortcut')</span>
</a>
