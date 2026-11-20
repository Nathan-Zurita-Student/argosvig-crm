/**
 * This will track all the images and fonts for publishing.
 */
import.meta.glob(["../images/**", "../fonts/**"]);

/**
 * Main vue bundler.
 */
import { createApp } from "vue/dist/vue.esm-bundler";

/**
 * Turbo Drive swaps the page body on navigation instead of reloading the
 * whole document, so moving between modules keeps the loaded assets.
 */
import * as Turbo from "@hotwired/turbo";

/**
 * Root component shared by every Vue application instance.
 */
const rootComponent = {
    data() {
        return {
            isMenuActive: false,

            hoveringMenu: '',

            lastScrollY: 0,
        };
    },

    created() {
        window.addEventListener('click', this.handleFocusOut);
        this.lastScrollY = Math.max((window.scrollY ?? window.pageYOffset ?? 0), 0);
        document.body.classList.remove('is-scrolling-down');
        window.addEventListener('scroll', this.handleScrollDirection, { passive: true });

        // Keep initial load in normal state when page is at/near top.
        this.handleScrollDirection();
    },

    beforeDestroy() {
        window.removeEventListener('click', this.handleFocusOut);
        window.removeEventListener('scroll', this.handleScrollDirection);
        document.body.classList.remove('is-scrolling-down');
    },

    beforeUnmount() {
        window.removeEventListener('click', this.handleFocusOut);
        window.removeEventListener('scroll', this.handleScrollDirection);
        document.body.classList.remove('is-scrolling-down');
    },

    methods: {
        onSubmit() {},

        onInvalidSubmit({ values, errors, results }) {
            setTimeout(() => {
                const errorKeys = Object.entries(errors)
                    .map(([key, value]) => ({ key, value }))
                    .filter(error => error["value"].length);

                let firstErrorElement = document.querySelector('[name="' + errorKeys[0]["key"] + '"]');

                firstErrorElement.scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });
            }, 100);
        },

        /**
         * Closes an open submenu flyout when the click lands outside the
         * sidebar. The sidebar width itself is owned solely by the collapse
         * toggle, so nothing here touches `sidebar-collapsed`.
         */
        handleFocusOut(event) {
            const sidebar = this.$refs.sidebar;

            if (
                sidebar &&
                !sidebar.contains(event.target)
            ) {
                this.isMenuActive = false;
            }
        },

        handleScrollDirection() {
            const topThreshold = 0;
            const currentScrollY = Math.max((window.scrollY ?? window.pageYOffset ?? 0), 0);
            const scrollDelta = currentScrollY - this.lastScrollY;

            if (currentScrollY == topThreshold) {
                document.body.classList.remove('is-scrolling-down');
                this.lastScrollY = currentScrollY;

                return;
            }

            if (Math.abs(scrollDelta) < 2) {
                return;
            }

            if (scrollDelta > 0) {
                document.body.classList.add('is-scrolling-down');
            }

            this.lastScrollY = currentScrollY;
        },
    },
};

/**
 * Global plugins.
 */
import Admin from "./plugins/admin";
import Axios from "./plugins/axios";
import Emitter from "./plugins/emitter";
import Flatpickr from "./plugins/flatpickr";
import VeeValidate from "./plugins/vee-validate";
import CreateElement from "./plugins/createElement";
import Draggable from "./plugins/draggable";
import VueCal from "./plugins/vue-cal";
import SearchableSelect from "./plugins/searchable-select";

/**
 * Global directives.
 */
import Debounce from "./directives/debounce";
import DOMPurify from "./directives/dompurify";
import ToolTip from "./directives/tooltip";

/**
 * Builds a fresh application with every global plugin and directive. Each
 * page registers its components on `window.app` before the layout mounts it,
 * so a new instance is needed for every page Turbo renders.
 */
export const createAdminApp = () => {
    const app = createApp(rootComponent);

    [
        Admin,
        Axios,
        Emitter,
        CreateElement,
        Draggable,
        Flatpickr,
        VeeValidate,
        VueCal,
        SearchableSelect,
    ].forEach((plugin) => app.use(plugin));

    app.directive("debounce", Debounce);
    app.directive("safe-html", DOMPurify);
    app.directive("tooltip", ToolTip);

    return app;
};

window.app = createAdminApp();

let mountedApp = null;

/**
 * Called by the layout as its last module script, after every component
 * script of the page has run.
 */
window.mountAdminApp = () => {
    if (mountedApp === window.app) {
        return;
    }

    window.app.mount("#app");

    mountedApp = window.app;
};

/**
 * Routes that answer with a file (downloads, exports, PDFs and prints) are
 * left to the browser; through Turbo they would be requested twice.
 */
const FILE_ROUTE = /(download|export|print|contract-report)/i;

const isFileRoute = (url) => FILE_ROUTE.test(new URL(url, window.location.href).pathname);

document.addEventListener("turbo:click", (event) => {
    if (isFileRoute(event.detail.url)) {
        event.preventDefault();
    }
});

/**
 * Navigates inside the admin without a full reload when Turbo is driving.
 */
window.visitAdmin = (url) => {
    if (Turbo.session.drive && ! isFileRoute(url)) {
        Turbo.visit(url);

        return;
    }

    window.location.assign(url);
};

/**
 * Forms keep their regular submission; only those marked with
 * `data-turbo="true"` are sent through Turbo.
 */
Turbo.config.forms.mode = "optin";

Turbo.config.drive.progressBarDelay = 150;

/**
 * Tears down the current page before Turbo swaps the body: the old Vue tree
 * is unmounted, listeners left on the shared emitter are dropped and a new
 * application receives the components of the incoming page.
 */
document.addEventListener("turbo:before-render", (event) => {
    if (mountedApp) {
        mountedApp.unmount();

        mountedApp = null;
    }

    window.emitter?.all.clear();

    // Language first: the plugins read it while the new application is built.
    const { locale, dir } = event.detail.newBody?.dataset ?? {};

    if (locale) {
        document.documentElement.lang = locale;
    }

    if (dir) {
        document.documentElement.dir = dir;
    }

    window.app = createAdminApp();
});

