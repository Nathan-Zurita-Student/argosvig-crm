{{--
    Mail folders, reached from the header on desktop. Below the desktop breakpoint the
    side drawer lists them, so this menu is hidden there.
--}}
@if ($mailMenu = menu()->getAdminMenuByKey('mail'))
    <x-admin::dropdown
        position="bottom-{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'left' : 'right' }}"
        class="max-lg:hidden"
    >
        <x-slot:toggle>
            <button
                type="button"
                @class([
                    'flex rounded-md p-1.5 transition-all hover:bg-gray-100 dark:hover:bg-gray-950',
                    'text-brandColor dark:text-blue-300' => $mailMenu->isActive(),
                ])
                title="{{ $mailMenu->getName() }}"
                aria-label="{{ $mailMenu->getName() }}"
                aria-haspopup="menu"
            >
                <span class="icon-mail text-2xl"></span>
            </button>
        </x-slot>

        <x-slot:content class="mt-2 min-w-[220px] !p-1.5">
            <p class="px-3 pb-1 pt-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ $mailMenu->getName() }}
            </p>

            <nav class="grid gap-0.5 text-sm">
                @foreach ($mailMenu->getChildren() as $folder)
                    <a
                        href="{{ $folder->getUrl() }}"
                        role="menuitem"
                        @class([
                            'flex items-center rounded-lg px-3 py-2 font-medium transition-colors',
                            'bg-blue-50 text-brandColor dark:bg-gray-800 dark:text-blue-300' => $folder->isActive(),
                            'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => ! $folder->isActive(),
                        ])
                    >
                        {{ $folder->getName() }}
                    </a>
                @endforeach
            </nav>
        </x-slot>
    </x-admin::dropdown>
@endif
