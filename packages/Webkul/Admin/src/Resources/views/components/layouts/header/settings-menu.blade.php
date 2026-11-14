{{--
    The two configuration areas, side by side with a short description so the choice
    is clear: Administration (users, permissions, pipelines...) and System preferences
    (language, branding, mail server...). Hidden below the desktop breakpoint, where
    the side drawer lists both.
--}}
@php
    $settingsMenus = menu()->getAdminMenuByKey(['settings', 'configuration'])->sortBy(fn ($item) => $item->getPosition());

    $descriptions = [
        'settings' => trans('admin::app.layouts.settings-info'),
        'configuration' => trans('admin::app.layouts.configuration-info'),
    ];
@endphp

@if ($settingsMenus->isNotEmpty())
    <x-admin::dropdown
        position="bottom-{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'left' : 'right' }}"
        class="max-lg:hidden"
    >
        <x-slot:toggle>
            <button
                type="button"
                @class([
                    'flex rounded-md p-1.5 transition-all hover:bg-gray-100 dark:hover:bg-gray-950',
                    'text-brandColor dark:text-blue-300' => $settingsMenus->contains(fn ($item) => $item->isActive()),
                ])
                title="{{ $settingsMenus->map->getName()->join(' · ') }}"
                aria-label="{{ $settingsMenus->map->getName()->join(' · ') }}"
                aria-haspopup="menu"
            >
                <span class="icon-setting text-2xl"></span>
            </button>
        </x-slot>

        <x-slot:content class="mt-2 w-[300px] !p-1.5">
            <nav class="grid gap-0.5">
                @foreach ($settingsMenus as $settingsMenu)
                    <a
                        href="{{ $settingsMenu->getUrl() }}"
                        role="menuitem"
                        @class([
                            'flex items-start gap-3 rounded-lg px-3 py-2.5 transition-colors',
                            'bg-blue-50 dark:bg-gray-800' => $settingsMenu->isActive(),
                            'hover:bg-gray-100 dark:hover:bg-gray-800' => ! $settingsMenu->isActive(),
                        ])
                    >
                        <span class="{{ $settingsMenu->getIcon() }} mt-0.5 text-xl text-brandColor dark:text-blue-300"></span>

                        <span class="grid gap-0.5">
                            <span class="text-sm font-semibold text-gray-800 dark:text-white">
                                {{ $settingsMenu->getName() }}
                            </span>

                            <span class="text-xs leading-snug text-gray-500 dark:text-gray-400">
                                {{ $descriptions[$settingsMenu->getKey()] ?? '' }}
                            </span>
                        </span>
                    </a>
                @endforeach
            </nav>
        </x-slot>
    </x-admin::dropdown>
@endif
