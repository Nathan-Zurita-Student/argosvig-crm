<x-admin::layouts>
    <x-slot:title>@lang('admin::two_factor.title')</x-slot>

    <section v-pre class="flex flex-col gap-4 rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-bold dark:text-white">@lang('admin::two_factor.title')</h1>
            <a class="secondary-button" href="{{ route('admin.user.account.edit') }}">@lang('admin::two_factor.account')</a>
        </div>

        @if ($errors->any())
            <ul role="alert" class="text-sm text-red-700 dark:text-red-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @if (isset($recoveryCodes))
            <div class="rounded-lg border border-gray-300 p-4 dark:border-gray-800">
                <p class="mb-2 font-medium">@lang('admin::two_factor.recovery_description')</p>
                <ul class="grid gap-2 sm:grid-cols-2">
                    @foreach ($recoveryCodes as $code)
                        <li><code>{{ $code }}</code></li>
                    @endforeach
                </ul>
            </div>
        @elseif ($enabled)
            <p>@lang('admin::two_factor.enabled')</p>
            <form method="POST" action="{{ route('admin.user.account.two_factor.disable') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                @method('DELETE')
                <label>
                    @lang('admin::two_factor.password')
                    <input type="password" name="password" autocomplete="current-password" required class="block rounded-md border border-gray-300 bg-white p-2 dark:border-gray-800 dark:bg-gray-950">
                </label>
                <label>
                    @lang('admin::two_factor.code')
                    <input name="verification_code" autocomplete="one-time-code" required maxlength="32" class="block rounded-md border border-gray-300 bg-white p-2 dark:border-gray-800 dark:bg-gray-950">
                </label>
                <button class="secondary-button">@lang('admin::two_factor.disable')</button>
            </form>
        @elseif ($secret)
            <p>@lang('admin::two_factor.setup')</p>
            <p><code>{{ $secret }}</code></p>
            <form method="POST" action="{{ route('admin.user.account.two_factor.enable') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <label>
                    @lang('admin::two_factor.app_code')
                    <input name="verification_code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required
                        class="block rounded-md border border-gray-300 bg-white p-2 dark:border-gray-800 dark:bg-gray-950">
                </label>
                <button class="primary-button">@lang('admin::two_factor.enable')</button>
            </form>
        @else
            <p>@lang('admin::two_factor.start_description')</p>
            <form method="POST" action="{{ route('admin.user.account.two_factor.begin') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <label>
                    @lang('admin::two_factor.password')
                    <input type="password" name="password" autocomplete="current-password" required class="block rounded-md border border-gray-300 bg-white p-2 dark:border-gray-800 dark:bg-gray-950">
                </label>
                <button class="primary-button">@lang('admin::two_factor.begin')</button>
            </form>
        @endif
    </section>
</x-admin::layouts>
