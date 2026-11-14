@php
    /**
     * Mail, Administration and System preferences are reached from the header on
     * desktop, which keeps the sidebar for the daily work areas.
     */
    $headerMenuKeys = ['mail', 'settings', 'configuration'];

    $itemClasses = fn (bool $active) => 'flex w-full items-center gap-2 rounded-lg p-1.5 text-start font-medium transition-colors '
        .($active ? 'bg-brandColor text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-950');
@endphp

<div
    id="admin-sidebar"
    ref="sidebar"
    class="duration-80 fixed top-[60px] z-[10002] flex h-[calc(100vh-60px)] w-[220px] flex-col border-gray-300 bg-white py-3 transition-all group-[.sidebar-collapsed]/container:w-[70px] dark:border-gray-800 dark:bg-gray-900 max-lg:hidden ltr:border-r rtl:border-l"
>
    <div class="journal-scroll min-h-0 flex-1 overflow-x-hidden overflow-y-auto group-[.sidebar-collapsed]/container:overflow-visible">
        <nav class="sidebar-rounded grid w-full gap-1">
            <!-- Navigation Menu -->
            @foreach (menu()->getItems('admin') as $menuItem)
                @continue(in_array($menuItem->getKey(), $headerMenuKeys))

                @if ($menuItem->haveChildren())
                    @php
                        $trigger = '<span class="'.e($menuItem->getIcon()).' text-2xl"></span>'
                            .'<span class="flex-1 truncate group-[.sidebar-collapsed]/container:hidden">'.e($menuItem->getName()).'</span>'
                            .'<span class="icon-right-arrow rtl:icon-left-arrow text-lg group-[.sidebar-collapsed]/container:hidden '.($menuItem->isActive() ? 'text-white/80' : 'text-gray-400').'"></span>';
                    @endphp

                    <!-- Group: its entries open in a floating panel beside the item -->
                    <v-sidebar-flyout
                        title="{{ $menuItem->getName() }}"
                        trigger-class="{{ $itemClasses($menuItem->isActive()) }}"
                    >
                        <div class="px-3">
                            <button
                                type="button"
                                class="{{ $itemClasses($menuItem->isActive()) }}"
                            >
                                {!! $trigger !!}
                            </button>
                        </div>

                        <template v-slot:trigger>
                            {!! $trigger !!}
                        </template>

                        <template v-slot:panel>
                            @foreach ($menuItem->getChildren() as $subMenuItem)
                                <a
                                    href="{{ $subMenuItem->getUrl() }}"
                                    role="menuitem"
                                    @class([
                                        'flex items-center rounded-lg px-3 py-2 font-medium transition-colors',
                                        'bg-blue-50 text-brandColor dark:bg-gray-800 dark:text-blue-300' => $subMenuItem->isActive(),
                                        'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' => ! $subMenuItem->isActive(),
                                    ])
                                >
                                    {{ $subMenuItem->getName() }}
                                </a>
                            @endforeach
                        </template>
                    </v-sidebar-flyout>
                @else
                    <div class="px-3">
                        <a
                            href="{{ $menuItem->getUrl() }}"
                            class="{{ $itemClasses($menuItem->isActive()) }} group/link relative"
                            aria-label="{{ $menuItem->getName() }}"
                        >
                            <span class="{{ $menuItem->getIcon() }} text-2xl"></span>

                            <span class="flex-1 truncate group-[.sidebar-collapsed]/container:hidden">{{ $menuItem->getName() }}</span>

                            <!-- Name shown on hover while the sidebar is collapsed to icons -->
                            <span class="pointer-events-none absolute top-1/2 z-[10005] hidden -translate-y-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1.5 text-xs font-medium text-white shadow-lg group-[.sidebar-collapsed]/container:group-hover/link:block dark:bg-gray-700 ltr:left-full ltr:ml-3 rtl:right-full rtl:mr-3">
                                {{ $menuItem->getName() }}
                            </span>
                        </a>
                    </div>
                @endif
            @endforeach
        </nav>
    </div>
</div>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-sidebar-flyout-template"
    >
        <div
            class="px-3"
            @mouseenter="scheduleOpen"
            @mouseleave="scheduleClose"
            @keydown.esc="close"
        >
            <button
                type="button"
                ref="trigger"
                aria-haspopup="menu"
                :aria-expanded="isOpen ? 'true' : 'false'"
                :class="triggerClass"
                @click="toggle($event)"
            >
                <slot name="trigger"></slot>
            </button>

            <teleport to="body">
                <transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 ltr:-translate-x-1 rtl:translate-x-1"
                    enter-to-class="opacity-100 translate-x-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="isOpen"
                        ref="panel"
                        role="menu"
                        class="fixed z-[10004] grid min-w-[220px] max-w-[280px] gap-0.5 rounded-xl border border-gray-200 bg-white p-1.5 text-sm shadow-xl dark:border-gray-800 dark:bg-gray-900"
                        :style="panelStyle"
                        @mouseenter="cancelClose"
                        @mouseleave="scheduleClose"
                        @keydown.esc="closeAndFocusTrigger"
                    >
                        <p
                            class="px-3 pb-1 pt-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            v-text="title"
                        ></p>

                        <slot name="panel"></slot>
                    </div>
                </transition>
            </teleport>
        </div>
    </script>

    <script type="module">
        app.component('v-sidebar-flyout', {
            template: '#v-sidebar-flyout-template',

            props: {
                title: {
                    type: String,
                    required: true,
                },

                triggerClass: {
                    type: String,
                    default: '',
                },
            },

            data() {
                return {
                    isOpen: false,

                    /**
                     * Opened by a click: stays open when the pointer leaves, until a
                     * second click, a click outside or Escape.
                     */
                    isPinned: false,

                    panelStyle: {},
                };
            },

            created() {
                this.timer = null;
            },

            mounted() {
                this.$emitter.on('sidebar-flyout-opened', this.closeOthers);

                document.addEventListener('click', this.handleOutsideClick);

                window.addEventListener('resize', this.close);
            },

            beforeUnmount() {
                clearTimeout(this.timer);

                this.$emitter.off('sidebar-flyout-opened', this.closeOthers);

                document.removeEventListener('click', this.handleOutsideClick);

                window.removeEventListener('resize', this.close);
            },

            methods: {
                /**
                 * A short delay keeps the panel from flashing while the pointer crosses
                 * the sidebar, and lets it travel from the item to the panel.
                 */
                scheduleOpen() {
                    clearTimeout(this.timer);

                    if (! this.isOpen) {
                        this.timer = setTimeout(this.open, 90);
                    }
                },

                scheduleClose() {
                    clearTimeout(this.timer);

                    if (! this.isPinned) {
                        this.timer = setTimeout(this.close, 220);
                    }
                },

                cancelClose() {
                    clearTimeout(this.timer);
                },

                toggle(event) {
                    clearTimeout(this.timer);

                    if (this.isOpen && this.isPinned) {
                        this.close();

                        return;
                    }

                    this.open();

                    this.isPinned = true;

                    // Opened from the keyboard (Enter or Space): move focus into the panel.
                    if (event?.detail === 0) {
                        this.$nextTick(() => this.$refs.panel?.querySelector('a')?.focus());
                    }
                },

                closeAndFocusTrigger() {
                    this.close();

                    this.$refs.trigger?.focus();
                },

                open() {
                    if (this.isOpen) {
                        return;
                    }

                    this.place();

                    this.isOpen = true;

                    this.$emitter.emit('sidebar-flyout-opened', this);

                    this.$nextTick(this.keepInViewport);
                },

                close() {
                    clearTimeout(this.timer);

                    this.isOpen = false;

                    this.isPinned = false;
                },

                closeOthers(opened) {
                    if (opened !== this) {
                        this.close();
                    }
                },

                /**
                 * The sidebar scrolls and clips its content, so the panel is fixed on the
                 * page, aligned with the item and just outside the sidebar edge.
                 */
                place() {
                    const trigger = this.$refs.trigger.getBoundingClientRect();

                    const sidebar = this.$el.closest('#admin-sidebar')?.getBoundingClientRect() ?? trigger;

                    const isRtl = document.documentElement.dir === 'rtl';

                    this.panelStyle = {
                        top: `${Math.round(trigger.top - 6)}px`,
                        [isRtl ? 'right' : 'left']: `${Math.round(isRtl ? window.innerWidth - sidebar.left + 8 : sidebar.right + 8)}px`,
                    };
                },

                keepInViewport() {
                    const panel = this.$refs.panel;

                    if (! panel) {
                        return;
                    }

                    const overflow = panel.getBoundingClientRect().bottom - (window.innerHeight - 12);

                    if (overflow > 0) {
                        this.panelStyle = {
                            ...this.panelStyle,
                            top: `${Math.max(68, parseInt(this.panelStyle.top) - overflow)}px`,
                        };
                    }
                },

                handleOutsideClick(event) {
                    if (
                        this.isOpen
                        && ! this.$el.contains(event.target)
                        && ! this.$refs.panel?.contains(event.target)
                    ) {
                        this.close();
                    }
                },
            },
        });
    </script>

    <script
        type="text/x-template"
        id="v-sidebar-collapse-template"
    >
        <button
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-brandColor focus:outline-none focus-visible:ring-2 focus-visible:ring-brandColor/40 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white"
            :title="isCollapsed
                ? '@lang('admin::app.layouts.sidebar.expand')'
                : '@lang('admin::app.layouts.sidebar.collapse')'"
            :aria-label="isCollapsed
                ? '@lang('admin::app.layouts.sidebar.expand')'
                : '@lang('admin::app.layouts.sidebar.collapse')'"
            :aria-expanded="! isCollapsed"
            @click="toggle"
        >
            <span
                class="icon-left-arrow text-lg transition-transform"
                :class="[isCollapsed ? 'ltr:rotate-[180deg] rtl:rotate-[0]' : 'ltr:rotate-[0] rtl:rotate-[180deg]']"
            ></span>
        </button>
    </script>

    <script type="module">
        app.component('v-sidebar-collapse', {
            template: '#v-sidebar-collapse-template',

            data() {
                return {
                    isCollapsed: {{ request()->cookie('sidebar_collapsed') ?? 0 }},
                }
            },

            methods: {
                toggle() {
                    this.isCollapsed = parseInt(this.isCollapsedCookie()) ? 0 : 1;

                    var expiryDate = new Date();

                    expiryDate.setMonth(expiryDate.getMonth() + 1);

                    document.cookie = 'sidebar_collapsed=' + this.isCollapsed + '; path=/; expires=' + expiryDate.toGMTString();

                    this.$root.$refs.appLayout.classList.toggle('sidebar-collapsed');

                    this.$root.$refs.appLayout.classList.toggle('sidebar-not-collapsed');
                },

                isCollapsedCookie() {
                    const cookies = document.cookie.split(';');

                    for (const cookie of cookies) {
                        const [name, value] = cookie.trim().split('=');

                        if (name === 'sidebar_collapsed') {
                            return value;
                        }
                    }

                    return 0;
                },
            },
        });
    </script>
@endPushOnce
