/*
 * Portfolio OS front-end runtime.
 *
 * Livewire + Alpine boot from `@livewireScripts` in the layout.
 * Alpine resolves `x-data="osShell()"` style expressions against `window`, so
 * every behaviour below is registered as a global factory instead of an
 * Alpine.data() plugin. That keeps this file bundler-only — no npm runtime deps.
 */

const STORE = {
    theme: 'os.theme',
    density: 'os.density',
    sidebar: 'os.sidebar',
};

const read = (key, fallback) => {
    try {
        return window.localStorage.getItem(key) ?? fallback;
    } catch {
        return fallback;
    }
};

const write = (key, value) => {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        /* private mode — preference is session-only */
    }
};

const applyTheme = (theme) => {
    const dark =
        theme === 'dark' ||
        (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
};

const applyDensity = (density) => {
    document.documentElement.dataset.density = density;
};

window.osApplyTheme = applyTheme;

/* ---------------------------------------------------------------- shell -- */

window.osShell = () => ({
    theme: read(STORE.theme, 'system'),
    density: read(STORE.density, 'comfortable'),
    collapsed: read(STORE.sidebar, 'open') === 'collapsed',
    drawer: false,

    init() {
        applyTheme(this.theme);
        applyDensity(this.density);

        this.media = window.matchMedia('(prefers-color-scheme: dark)');
        this.mediaListener = () => this.theme === 'system' && applyTheme('system');
        this.media.addEventListener('change', this.mediaListener);

        // Livewire SPA navigation swaps <body>; re-assert preferences after.
        document.addEventListener('livewire:navigated', () => {
            applyTheme(this.theme);
            applyDensity(this.density);
        });
    },

    get isDark() {
        return document.documentElement.classList.contains('dark');
    },

    setTheme(theme) {
        this.theme = theme;
        write(STORE.theme, theme);
        applyTheme(theme);
    },

    toggleTheme() {
        this.setTheme(this.isDark ? 'light' : 'dark');
    },

    toggleDensity() {
        this.density = this.density === 'compact' ? 'comfortable' : 'compact';
        write(STORE.density, this.density);
        applyDensity(this.density);
    },

    toggleSidebar() {
        this.collapsed = !this.collapsed;
        write(STORE.sidebar, this.collapsed ? 'collapsed' : 'open');
    },

    openDrawer() {
        this.drawer = true;
    },

    closeDrawer() {
        this.drawer = false;
    },
});

/* --------------------------------------------------------------- toasts -- */

let toastId = 0;

window.osToasts = () => ({
    items: [],

    push(detail) {
        const payload = Array.isArray(detail) ? detail[0] : detail;
        const message = typeof payload === 'string' ? payload : payload?.message;

        if (!message) return;

        const id = ++toastId;
        const tone = (typeof payload === 'object' && payload?.tone) || 'success';
        const timeout = tone === 'danger' ? 7000 : 4000;

        this.items.push({ id, message, tone, action: payload?.action ?? null, href: payload?.href ?? null });

        setTimeout(() => this.dismiss(id), timeout);
    },

    dismiss(id) {
        this.items = this.items.filter((item) => item.id !== id);
    },
});

/* ------------------------------------------------------------- palette -- */

window.osPalette = () => ({
    open: false,
    active: 0,
    query: '',
    quickLabels: [],
    navLabels: [],

    matches(text) {
        if (!this.query) return true;

        return String(text).toLowerCase().includes(this.query.trim().toLowerCase());
    },

    quickVisible() {
        return this.quickLabels.some((label) => this.matches(label));
    },

    navVisible() {
        return this.navLabels.some((label) => this.matches(label));
    },

    init() {
        document.addEventListener('livewire:navigated', () => this.close());
    },

    toggle() {
        this.open ? this.close() : this.show();
    },

    show() {
        this.open = true;
        this.active = 0;
        this.$nextTick(() => {
            this.$refs.input?.focus();
            this.$refs.input?.select();
            this.move(0);
        });
    },

    close() {
        if (!this.open) return;
        this.open = false;
        this.query = '';
        this.$wire?.set('q', '', false);
    },

    items() {
        return Array.from(this.$refs.results?.querySelectorAll('[data-palette-item]') ?? []).filter(
            (el) => el.offsetParent !== null,
        );
    },

    move(step) {
        const items = this.items();
        if (!items.length) return;

        this.active = (this.active + step + items.length) % items.length;
        items[this.active]?.scrollIntoView({ block: 'nearest' });
        items.forEach((el, index) => el.setAttribute('aria-selected', index === this.active ? 'true' : 'false'));
    },

    choose() {
        const items = this.items();
        (items[this.active] ?? items[0])?.click();
    },

    reset() {
        this.active = 0;
        this.$nextTick(() => this.move(0));
    },
});

/* --------------------------------------------------------- inline edit -- */

window.osInlineEdit = (initial = '') => ({
    editing: false,
    draft: initial,

    start() {
        this.editing = true;
        this.$nextTick(() => {
            const field = this.$refs.field;
            field?.focus();
            field?.select?.();
        });
    },

    cancel() {
        this.editing = false;
        this.draft = initial;
    },

    done() {
        this.editing = false;
    },
});

/* --------------------------------------------------------------- utils -- */

window.osCopy = () => ({
    copied: false,

    async copy(value) {
        try {
            await navigator.clipboard.writeText(value);
            this.copied = true;
            setTimeout(() => (this.copied = false), 1600);
        } catch {
            this.copied = false;
        }
    },
});

/* ------------------------------------------------------------ list nav -- */

/*
 * j/k walk a cursor down any list of `[data-list-row]`s on the page, Enter
 * opens it. The cursor lives in the DOM (not Alpine state) so a Livewire
 * re-render simply drops it instead of pointing at a stale row.
 */
const listRows = () =>
    Array.from(document.querySelectorAll('[data-list-row]')).filter((row) => row.offsetParent !== null);

window.osMoveListCursor = (step) => {
    const rows = listRows();
    if (!rows.length) return;

    const current = rows.findIndex((row) => row.hasAttribute('data-list-cursor'));
    const start = current === -1 ? (step > 0 ? -1 : rows.length) : current;
    const next = Math.min(Math.max(start + step, 0), rows.length - 1);

    rows.forEach((row) => row.removeAttribute('data-list-cursor'));
    rows[next].setAttribute('data-list-cursor', '');
    rows[next].scrollIntoView({ block: 'nearest' });
};

window.osOpenListCursor = () => {
    const active = document.activeElement;

    // Never hijack Enter from a focused control.
    if (active && active !== document.body && active.closest('a,button,[role="button"]')) return false;

    const row = document.querySelector('[data-list-row][data-list-cursor]');
    const link = row?.querySelector('a[href]');

    if (!link) return false;

    link.click();

    return true;
};
