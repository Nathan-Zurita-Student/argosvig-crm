<!DOCTYPE html>

<html
    class="{{ request()->cookie('dark_mode') ? 'dark' : '' }}"
    lang="{{ app()->getLocale() }}"
    dir="{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'rtl' : 'ltr' }}"
>

<head>

    {!! view_render_event('admin.layout.head.before') !!}

    <title>{{ $title }} | Argosvig</title>

    <meta charset="UTF-8">

    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge"
    >
    <meta
        http-equiv="content-language"
        content="{{ app()->getLocale() }}"
    >

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="base-url"
        content="{{ url()->to('/') }}"
    >
    <meta
        name="currency"
        content="{{
            json_encode([
                'code' => config('app.currency'),
                'symbol' => core()->currencySymbol(config('app.currency'))])
            }}
        "
    >

    {{--
        Broadcasting settings for the javascript client, rendered only when a socket driver is
        fully configured. Nothing is baked into the asset bundle, so rotating a key or switching
        driver takes effect on the next page load; the values here are the ones a client must
        present to subscribe, never the application secret.
    --}}
    @if ($broadcasting = \Webkul\Admin\Services\NotificationDriver::clientConfig())
        <meta
            name="broadcasting"
            content="{{ json_encode($broadcasting) }}"
        >

        {{-- Echo and its websocket client are only fetched when there is a socket to open. --}}
        {{ vite()->set(['src/Resources/assets/js/echo.js']) }}
    @endif

    {{--
        Turbo Drive: pages are swapped without a reload. The snapshot cache stays off
        because Vue replaces the in-DOM templates once mounted, and links are not
        prefetched on hover. A new asset build changes the tracked meta and forces a
        full reload on the next visit.
    --}}
    <meta
        name="turbo-cache-control"
        content="no-cache"
    >
    <meta
        name="turbo-prefetch"
        content="false"
    >
    <meta
        name="admin-assets"
        content="{{ is_file($adminManifest = public_path('admin/build/manifest.json')) ? filemtime($adminManifest) : 'dev' }}"
        data-turbo-track="reload"
    >

    @stack('meta')

    {{
        vite()->set(['src/Resources/assets/css/app.css', 'src/Resources/assets/js/app.js'])
    }}

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap"
        rel="stylesheet"
    />

    @if ($favicon = core()->getConfigData('general.general.admin_logo.favicon_image'))
        <link
            type="image/svg+xml"
            href="{{ Storage::url($favicon) }}"
            rel="shortcut icon"
            sizes="16x16"
        >
    @else
        <link
            type="image/svg+xml"
            href="{{ vite()->asset('images/favicon.svg') }}"
            rel="shortcut icon"
            sizes="16x16"
        />
    @endif

    @php
        $brandColor = core()->getConfigData('general.settings.menu_color.brand_color') ?? '#244A96';
    @endphp

    @stack('styles')

    <style>
        :root {
            --brand-color: {{ $brandColor }};
        }

        {!! core()->getConfigData('general.content.custom_scripts.custom_css') !!}
    </style>

    {!! view_render_event('admin.layout.head.after') !!}
</head>

<body
    class="h-full font-inter dark:bg-gray-950"
    data-locale="{{ app()->getLocale() }}"
    data-dir="{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'rtl' : 'ltr' }}"
>
    {!! view_render_event('admin.layout.body.before') !!}

    <div
        id="app"
        class="h-full"
    >
        <!-- Flash Message Blade Component -->
        <x-admin::flash-group />

        <!-- Confirm Modal Blade Component -->
        <x-admin::modal.confirm />

        {!! view_render_event('admin.layout.content.before') !!}

        <!-- Page Header Blade Component -->
        <x-admin::layouts.header />

        <div
            class="group/container {{ request()->cookie('sidebar_collapsed') ?? 0 ? 'sidebar-collapsed' : 'sidebar-not-collapsed' }} flex gap-4"
            ref="appLayout"
        >
            <!-- Page Sidebar Blade Component -->
            <x-admin::layouts.sidebar.desktop />

            <div class="flex min-h-[calc(100vh-62px)] max-w-full flex-1 flex-col bg-gray-100 pt-3 transition-all duration-300 dark:bg-gray-950">
                <!-- Page Content Blade Component -->
                <div class="px-4 pb-4 transition-all duration-300 lg:ltr:pl-[235px] lg:group-[.sidebar-collapsed]/container:ltr:pl-[85px] lg:rtl:pr-[235px] lg:group-[.sidebar-collapsed]/container:rtl:pr-[85px]">
                    {{ $slot }}
                </div>
            </div>
        </div>

        {!! view_render_event('admin.layout.content.after') !!}
    </div>

    {!! view_render_event('admin.layout.body.after') !!}

    @stack('scripts')

    {!! view_render_event('admin.layout.vue-app-mount.before') !!}

    {{--
        Module scripts run in document order, both on a full load and when Turbo
        inserts a new body, so this one runs after every component the page
        registered above.
    --}}
    <script type="module">
        window.mountAdminApp();
    </script>

    {!! view_render_event('admin.layout.vue-app-mount.after') !!}
</body>

</html>
