<x-admin::layouts.anonymous>
    <x-slot:title>@lang('admin::two_factor.title')</x-slot>

    <main class="flex min-h-screen items-center justify-center bg-gray-100 p-4 dark:bg-gray-950">
        <section class="w-full max-w-md rounded-xl border border-gray-300 bg-white p-6 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
            <h1 class="text-xl font-bold">@lang('admin::two_factor.title')</h1>
            <p class="mb-4 mt-2 text-sm">@lang('admin::two_factor.challenge')</p>

            <form method="POST" action="{{ route('admin.session.two_factor.verify') }}" class="flex flex-col gap-3">
                @csrf
                <label for="verification_code">@lang('admin::two_factor.code')</label>
                <input id="verification_code" name="verification_code" autocomplete="one-time-code" required maxlength="32"
                    class="rounded-md border border-gray-300 bg-white p-2 dark:border-gray-800 dark:bg-gray-950">
                @error('verification_code')
                    <p role="alert" class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>
                @enderror
                <div class="flex flex-wrap justify-between gap-2">
                    <a class="secondary-button" href="{{ route('admin.session.create') }}">@lang('admin::two_factor.back_to_login')</a>
                    <button class="primary-button">@lang('admin::two_factor.verify')</button>
                </div>
            </form>
        </section>
    </main>
</x-admin::layouts.anonymous>
