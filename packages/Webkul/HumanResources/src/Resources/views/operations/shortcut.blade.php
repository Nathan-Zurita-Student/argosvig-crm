<a href="{{ route('admin.operations.index') }}" class="secondary-button whitespace-nowrap" @if (request()->routeIs('admin.operations.*')) aria-current="page" @endif>
    @lang('human_resources::operations.shortcut')
</a>
