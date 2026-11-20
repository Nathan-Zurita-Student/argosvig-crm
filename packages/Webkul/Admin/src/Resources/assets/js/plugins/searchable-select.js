/**
 * Searchable selects for the whole admin.
 *
 * Every <select> that lists records (marked with `data-searchable`) or that has many
 * options gets a button in its place and, when opened, a panel with a search box in
 * the look of the CRM lookup. The original <select> stays in the form, hidden: it keeps
 * the value that is submitted, v-model and vee-validate keep listening to it, and each
 * choice made in the panel is written back to it with the usual input/change events.
 *
 * `data-searchable="false"` keeps a select native.
 */

const AUTO_MIN_OPTIONS = 8;
const SYNC_INTERVAL = 400;
const IGNORED = ".flatpickr-calendar, .tox, [data-searchable-ignore]";

const INPUT_CLASS =
    "w-full rounded border border-gray-300 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400 ltr:pr-8 rtl:pl-8";
const PANEL_CLASS =
    "fixed z-[10050] flex flex-col gap-2 rounded-lg border border-gray-300 bg-white p-2 text-sm shadow-lg dark:border-gray-900 dark:bg-gray-800";
const ITEM_CLASS =
    "flex cursor-pointer items-center justify-between gap-2 px-4 py-2 text-gray-800 transition-colors hover:bg-blue-100 dark:text-white dark:hover:bg-gray-900";
const ACTIVE_CLASSES = ["bg-blue-100", "dark:bg-gray-900"];

const states = new Set();
const bySelect = new WeakMap();

let started = false;
let open = null;
let texts = null;

const normalize = (value) =>
    (value || "")
        .normalize("NFD")
        .replace(/[̀-ͯ]/g, "")
        .toLowerCase()
        .trim();

const dispatch = (element, type) => element.dispatchEvent(new Event(type, { bubbles: true }));

const t = (key) => {
    if (! texts) {
        texts = { search: "Search...", noResults: "No results found", placeholder: "" };

        try {
            Object.assign(texts, JSON.parse(document.querySelector('meta[name="searchable-select"]')?.content || "{}"));
        } catch (error) {
            // The defaults above are enough.
        }
    }

    return texts[key];
};

const hasClass = (className, pattern) => pattern.test(` ${className} `);

/**
 * The trigger takes the select's own classes, so it keeps the same size, border and colours
 * as the field it replaces; the defaults fill only what the select did not set.
 */
const triggerClass = (select) => {
    const own = select.className || "";
    const extra = ["custom-select", "text-left", "cursor-pointer", "items-center", "ltr:pr-9", "rtl:pl-9", "disabled:cursor-not-allowed", "disabled:opacity-60"];

    if (! hasClass(own, /\sbg-/)) extra.push("bg-white");
    if (! hasClass(own, /\sdark:bg-/)) extra.push("dark:bg-gray-900");
    if (! hasClass(own, /\stext-(gray|slate|zinc|neutral|black|white)-?/)) extra.push("text-gray-800");
    if (! hasClass(own, /\sdark:text-/)) extra.push("dark:text-gray-300");
    if (! hasClass(own, /\s(block|flex|inline-flex|grid|hidden)\s/)) extra.push("inline-flex");

    return `${own} ${extra.join(" ")}`.trim();
};

const isEligible = (select) => {
    if (bySelect.has(select) || ! select.isConnected || select.closest(IGNORED)) {
        return false;
    }

    /**
     * Vue compiles the page from the HTML inside #app when it mounts, so a button added
     * before that would be copied into the new page without its listeners. Selects there
     * wait until the app is mounted; the nodes Vue renders then are picked up as they arrive.
     */
    const root = select.closest("#app");

    if (root && ! root.__vue_app__ && ! root.hasAttribute("data-v-app")) {
        return false;
    }

    // A copy of an enhanced select (from a template made of the live page) gets a fresh button.
    const copied = select.nextElementSibling;

    if (copied?.hasAttribute("data-searchable-trigger")) {
        copied.remove();
        select.style.display = "";
    }

    // Selects hidden on purpose (data sources for other widgets) stay as they are.
    if (select.hidden || select.style.display === "none" || select.classList.contains("hidden")) {
        return false;
    }

    const flag = select.dataset.searchable;

    if (flag === "false") {
        return false;
    }

    return flag !== undefined || select.options.length >= AUTO_MIN_OPTIONS;
};

const selectedOptions = (select) => Array.from(select.selectedOptions);

const labelOf = (select) => {
    const chosen = selectedOptions(select);

    if (select.multiple) {
        return chosen.length ? chosen.map((option) => option.text.trim()).join(", ") : t("placeholder");
    }

    return chosen[0]?.text.trim() ?? "";
};

const signature = (state) => {
    const { select } = state;

    return [
        select.className,
        select.matches(":disabled"),
        select.options.length,
        selectedOptions(select).map((option) => option.value).join("\u0001"),
        labelOf(select),
    ].join("\u0002");
};

const sync = (state, force = false) => {
    const { select, trigger, label } = state;

    if (! select.isConnected) {
        destroy(state);

        return;
    }

    const current = signature(state);

    if (! force && current === state.signature) {
        return;
    }

    state.signature = current;
    trigger.className = triggerClass(select);
    trigger.disabled = select.matches(":disabled");

    const text = labelOf(select);

    label.textContent = text || " ";
    trigger.title = text;

    if (state.error && selectedOptions(select).some((option) => option.value !== "")) {
        clearError(state);
    }

    if (open === state) {
        render(state);
    }
};

/**
 * v-model writes `value` / `selectedIndex` without firing events, so the select instance
 * reports those writes back to its button.
 */
const watchProperties = (state) => {
    const { select } = state;

    ["value", "selectedIndex"].forEach((property) => {
        const descriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, property);

        Object.defineProperty(select, property, {
            configurable: true,
            get() {
                return descriptor.get.call(this);
            },
            set(value) {
                descriptor.set.call(this, value);
                queueMicrotask(() => sync(state));
            },
        });
    });
};

const showError = (state) => {
    clearError(state);

    const message = document.createElement("p");

    message.className = "mt-1 text-xs italic text-red-600";
    message.textContent = state.select.validationMessage;
    state.trigger.after(message);
    state.trigger.classList.add("!border-red-600");
    state.error = message;
};

const clearError = (state) => {
    state.error?.remove();
    state.error = null;
    state.trigger.classList.remove("!border-red-600");
};

const enhance = (select) => {
    if (! isEligible(select)) {
        return;
    }

    const trigger = document.createElement("button");
    const label = document.createElement("span");

    trigger.type = "button";
    trigger.setAttribute("role", "combobox");
    trigger.setAttribute("aria-haspopup", "listbox");
    trigger.setAttribute("aria-expanded", "false");
    trigger.dataset.searchableTrigger = "";
    label.className = "block min-w-0 flex-1 truncate";
    trigger.append(label);

    // A select without a width of its own keeps the width the browser gave it.
    if (! hasClass(select.className, /\s(w|min-w)-/) && select.offsetWidth) {
        trigger.style.minWidth = `${select.offsetWidth}px`;
    }

    const state = { select, trigger, label, signature: null, error: null, panel: null, active: -1, items: [] };

    select.after(trigger);
    select.style.display = "none";
    bySelect.set(select, state);
    states.add(state);
    watchProperties(state);

    trigger.addEventListener("click", () => (open === state ? close() : show(state)));

    trigger.addEventListener("keydown", (event) => {
        if (["Enter", " ", "ArrowDown", "ArrowUp"].includes(event.key)) {
            event.preventDefault();
            show(state);
        } else if (event.key.length === 1 && ! event.ctrlKey && ! event.metaKey && ! event.altKey) {
            event.preventDefault();
            show(state, event.key);
        }
    });

    select.addEventListener("invalid", () => {
        showError(state);
        trigger.scrollIntoView({ block: "center" });
        trigger.focus();
    });

    sync(state, true);
};

const destroy = (state) => {
    if (open === state) {
        close(false);
    }

    clearError(state);
    state.trigger.remove();
    states.delete(state);

    if (state.select.isConnected) {
        delete state.select.value;
        delete state.select.selectedIndex;
        state.select.style.display = "";
    }
};

/**
 * The panel: a search box over the options, filtered as the user types.
 */
const buildPanel = (state) => {
    const panel = document.createElement("div");
    const searchBox = document.createElement("div");
    const search = document.createElement("input");
    const icon = document.createElement("span");
    const list = document.createElement("ul");

    panel.className = PANEL_CLASS;
    searchBox.className = "relative flex items-center";
    search.type = "text";
    search.className = INPUT_CLASS;
    search.placeholder = t("search");
    search.autocomplete = "off";
    search.setAttribute("aria-label", t("search"));
    icon.className = "icon-search pointer-events-none absolute text-xl text-gray-500 ltr:right-2 rtl:left-2";
    list.className = "relative max-h-60 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-700";
    list.setAttribute("role", "listbox");

    if (state.select.multiple) {
        list.setAttribute("aria-multiselectable", "true");
    }

    searchBox.append(search, icon);
    panel.append(searchBox, list);

    search.addEventListener("input", () => {
        state.active = -1;
        render(state);
    });

    search.addEventListener("keydown", (event) => {
        if (event.key === "ArrowDown" || event.key === "ArrowUp") {
            event.preventDefault();
            move(state, event.key === "ArrowDown" ? 1 : -1);
        } else if (event.key === "Enter") {
            event.preventDefault();

            const item = state.items[state.active] ?? (state.items.length === 1 ? state.items[0] : null);

            if (item) {
                choose(state, item.option);
            }
        } else if (event.key === "Escape") {
            event.preventDefault();
            close();
        } else if (event.key === "Tab") {
            close(false);
        }
    });

    // Keeps the focus in the search box while an option is clicked.
    list.addEventListener("mousedown", (event) => event.preventDefault());

    state.panel = panel;
    state.search = search;
    state.list = list;

    return panel;
};

const render = (state) => {
    const { select, list, search } = state;
    const term = normalize(search.value);
    const fragment = document.createDocumentFragment();

    state.items = [];

    const addOption = (option) => {
        if (term && ! normalize(option.text).includes(term)) {
            return false;
        }

        const item = document.createElement("li");
        const text = document.createElement("span");

        item.className = `${ITEM_CLASS}${option.disabled ? " pointer-events-none opacity-50" : ""}${option.selected ? " font-semibold" : ""}`;
        item.setAttribute("role", "option");
        item.setAttribute("aria-selected", option.selected ? "true" : "false");
        // Long names wrap instead of being cut, so two similar records can be told apart.
        text.className = "min-w-0 flex-1 break-words";
        text.textContent = option.text.trim() || " ";
        item.append(text);

        if (select.multiple) {
            const box = document.createElement("span");

            box.className = `${option.selected ? "icon-checkbox-select text-brandColor" : "icon-checkbox-outline text-gray-500"} text-2xl`;
            item.prepend(box);
        } else if (option.selected) {
            const tick = document.createElement("span");

            tick.className = "icon-tick text-xl text-brandColor";
            item.append(tick);
        }

        item.addEventListener("click", () => choose(state, option));
        item.addEventListener("mousemove", () => highlight(state, state.items.findIndex((entry) => entry.item === item)));

        fragment.append(item);
        state.items.push({ item, option });

        return true;
    };

    Array.from(select.children).forEach((child) => {
        if (child.tagName === "OPTGROUP") {
            const header = document.createElement("li");
            const start = fragment.childNodes.length;

            header.className = "px-4 pb-1 pt-2 text-xs font-semibold text-gray-500 dark:text-gray-400";
            header.setAttribute("role", "presentation");
            header.textContent = child.label;
            fragment.append(header);

            const shown = Array.from(child.children).filter((option) => addOption(option)).length;

            if (! shown) {
                fragment.childNodes[start].remove();
            }
        } else if (child.tagName === "OPTION") {
            addOption(child);
        }
    });

    if (! state.items.length) {
        const empty = document.createElement("li");

        empty.className = "px-4 py-2 text-gray-500";
        empty.textContent = t("noResults");
        fragment.append(empty);
    }

    list.replaceChildren(fragment);

    if (state.active >= state.items.length) {
        state.active = state.items.length - 1;
    }

    highlight(state, state.active);
};

const highlight = (state, index) => {
    state.items.forEach(({ item }, position) => {
        ACTIVE_CLASSES.forEach((name) => item.classList.toggle(name, position === index));
    });

    state.active = index;

    // Scrolls the list only; scrollIntoView could move the page as well.
    const item = state.items[index]?.item;
    const list = state.list;

    if (item) {
        if (item.offsetTop < list.scrollTop) {
            list.scrollTop = item.offsetTop;
        } else if (item.offsetTop + item.offsetHeight > list.scrollTop + list.clientHeight) {
            list.scrollTop = item.offsetTop + item.offsetHeight - list.clientHeight;
        }
    }
};

const move = (state, step) => {
    if (! state.items.length) {
        return;
    }

    const next = state.active < 0 ? (step > 0 ? 0 : state.items.length - 1) : (state.active + step + state.items.length) % state.items.length;

    highlight(state, next);
};

const choose = (state, option) => {
    const { select } = state;

    if (option.disabled) {
        return;
    }

    if (select.multiple) {
        option.selected = ! option.selected;
    } else {
        select.selectedIndex = option.index;
    }

    dispatch(select, "input");
    dispatch(select, "change");
    sync(state, true);

    if (select.multiple) {
        render(state);
        state.search.focus({ preventScroll: true });
    } else {
        close();
    }
};

const place = (state) => {
    const rect = state.trigger.getBoundingClientRect();

    if (! rect.width && ! rect.height) {
        close(false);

        return;
    }

    const panel = state.panel;
    const width = Math.min(Math.max(rect.width, 280), window.innerWidth - 16);
    const left = Math.min(Math.max(8, rect.left), window.innerWidth - width - 8);

    panel.style.width = `${width}px`;
    panel.style.left = `${Math.max(8, left)}px`;

    const height = panel.offsetHeight;
    const below = window.innerHeight - rect.bottom;

    panel.style.top = below < height + 12 && rect.top > below
        ? `${Math.max(8, rect.top - height - 4)}px`
        : `${rect.bottom + 4}px`;
};

const show = (state, initial = "") => {
    if (state.trigger.disabled) {
        return;
    }

    if (open && open !== state) {
        close(false);
    }

    if (! state.panel) {
        buildPanel(state);
    }

    open = state;
    state.search.value = initial;
    state.active = -1;
    state.panel.style.top = "-9999px";
    document.body.append(state.panel);
    render(state);
    place(state);

    highlight(state, initial ? -1 : state.items.findIndex(({ option }) => option.selected));
    state.trigger.setAttribute("aria-expanded", "true");
    state.search.focus({ preventScroll: true });
    follow(state);
};

/**
 * Keeps the panel under its field while it moves without a scroll event, as in a
 * drawer or modal that is still sliding in.
 */
const follow = (state) => {
    let last = "";

    const step = () => {
        if (open !== state) {
            return;
        }

        const rect = state.trigger.getBoundingClientRect();
        const key = `${rect.left},${rect.top},${rect.width}`;

        if (key !== last) {
            last = key;
            place(state);
        }

        requestAnimationFrame(step);
    };

    requestAnimationFrame(step);
};

const close = (focusTrigger = true) => {
    if (! open) {
        return;
    }

    const state = open;

    open = null;
    state.panel.remove();
    state.trigger.setAttribute("aria-expanded", "false");
    dispatch(state.select, "blur");

    if (focusTrigger && state.trigger.isConnected) {
        state.trigger.focus();
    }
};

const scan = (root) => {
    if (root.tagName === "SELECT") {
        enhance(root);
    } else if (root.querySelectorAll) {
        root.querySelectorAll("select").forEach(enhance);
    }
};

const start = () => {
    if (started) {
        return;
    }

    started = true;

    scan(document);

    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            const target = mutation.target;

            if (mutation.type === "childList") {
                mutation.addedNodes.forEach((node) => node.nodeType === 1 && scan(node));

                // Options added later (loaded or rendered after the select) may make it eligible.
                const select = target.closest?.("select");

                if (select) {
                    const state = bySelect.get(select);

                    state ? sync(state) : enhance(select);
                }
            } else if (target.tagName === "SELECT") {
                const state = bySelect.get(target);

                state ? sync(state) : enhance(target);
            }
        });
    }).observe(document.documentElement, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["class", "disabled", "data-searchable"],
    });

    // A safety net for what changes without a DOM mutation, such as option.selected set by Vue.
    setInterval(() => states.forEach((state) => sync(state)), SYNC_INTERVAL);

    document.addEventListener(
        "mousedown",
        (event) => {
            if (open && ! open.panel.contains(event.target) && ! open.trigger.contains(event.target)) {
                close(false);
            }
        },
        true,
    );

    document.addEventListener(
        "scroll",
        (event) => {
            if (open && ! open.panel.contains(event.target)) {
                place(open);
            }
        },
        true,
    );

    window.addEventListener("resize", () => open && place(open));

    // A label pointing at the hidden select opens its button instead.
    document.addEventListener(
        "click",
        (event) => {
            const label = event.target.closest?.("label");

            if (! label || event.target.closest("[data-searchable-trigger]")) {
                return;
            }

            const select = label.htmlFor ? document.getElementById(label.htmlFor) : label.querySelector("select");
            const state = select && bySelect.get(select);

            if (state) {
                event.preventDefault();
                state.trigger.focus();
            }
        },
        true,
    );

    document.addEventListener("turbo:before-render", () => close(false));
};

export default {
    install: () => {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", start, { once: true });
        } else {
            start();
        }
    },
};
