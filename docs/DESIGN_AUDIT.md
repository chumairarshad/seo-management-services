# Design consistency audit

**Score: 9 / 10** — One design system end to end: tokens in `resources/css/app.css`, a 28-component Blade library in `resources/views/components/`, one app shell (rail + top bar + command palette + mobile drawer/thumb bar), and one page pattern (`x-page-header` → filters → content → empty/loading state) used by every screen. Money no longer reads as a bolt-on. Remaining gap is depth of interaction on a few detail screens, not visual divergence.

Last full pass: front-end overhaul (design tokens, component library, app shell, page-by-page rework).

## The system

- **Tokens** (`resources/css/app.css`): `canvas` / `surface` / `raised` / `subtle` surfaces, `line` + `line-strong` borders, `ink` / `ink-soft` / `muted` / `faint` text tiers, one indigo accent (`accent`, `accent-solid`, `accent-soft`, `accent-line`) plus `success` / `warn` / `danger` / `info` each with `soft` and `line`. Dark mode is a full second token set under `.dark`, toggled per user and persisted in `localStorage` (painted before first frame, so no flash).
- **Type**: Instrument Sans for display/headings, Geist for UI text, Geist Mono for figures, IDs, labels and eyebrows. All self-hosted `woff2` subsets in `resources/fonts` — no runtime CDN, no build-time network fetch.
- **Density**: `--row-py` / `--nav-py` custom properties with a `[data-density='compact']` override, exposed as a "Compact rows" toggle in the user menu.
- **Numbers**: every money and metric figure renders through `x-money` or a `numeric` table cell — mono, `tabular-nums`, right-aligned, negatives in danger, signed deltas in success.
- **Motion**: 120–200ms transitions, `motion-safe:` on entrances, global `prefers-reduced-motion` kill switch.
- **Focus**: one global `:focus-visible` outline token; skip link; `aria-*` on all interactive shell elements.

## Component library (`resources/views/components/`)

`avatar` · `badge` · `bulk-bar` · `button` · `card` · `checkbox` · `dropdown` (+ `dropdown/item`) · `empty-state` · `file-input` · `filter-bar` · `icon` · `input` · `kbd` · `modal` · `money` · `page-header` · `progress` · `section` · `segmented` · `select` · `skeleton` · `spinner` · `stat` · `table` (+ `table/row`, `table/cell`) · `tabs` · `textarea` · `tooltip`, plus Livewire/Laravel pagination overrides in `resources/views/vendor/`.

## Previously logged issues

### High — all fixed

| Page / area | Element | Status |
|-------------|---------|--------|
| Mobile nav | Missing Money, Login history, Task templates | **Fixed** — drawer renders the same permission-filtered tree as the desktop rail, plus a five-slot thumb bar. |
| Money index tables | Raw `<button class="text-xs">` row actions | **Fixed** — icon `x-button`s with tooltips, same as Work. |
| Dashboard | Stale "wired for M5" caption | **Fixed** — dashboard rebuilt around "awaiting my approval", "due today", "expiring soon", "profit this month". |
| Money page titles | `text-2xl`, no eyebrow | **Fixed** — every page uses `x-page-header` (breadcrumb, title, subtitle, meta, actions). |
| Hand-rolled `<table>` | Tasks / Links / Attendance / Money | **Fixed** — zero raw `<table>` tags remain in `resources/views/livewire`. |
| Inline "No … yet" cells | Money, Distributions, P&L | **Fixed** — `x-empty-state` with icon, explanation and a next action. |
| Bare `<select>` / `<textarea>` | App-wide | **Fixed** — zero bare form controls remain; all use `x-select` / `x-textarea` / `x-input` with label, hint and error. |
| Card padding drift | `p-4` / `p-5` / `p-6` mix | **Fixed** — `x-card` owns padding (`sm` / `md` / `lg` / `none`). |
| `x-skeleton` unused | — | **Fixed** — table and card skeletons on every paginated index, shown on `wire:loading.delay.long`. |
| Back / quick links | Anchor styling instead of buttons | **Fixed** — `x-page-header` `back` prop + secondary `x-button`s. |

### Remaining / accepted

| Area | Note |
|------|------|
| Articles index | Intentionally a denser table now; card list dropped. |
| Native `<input type="month">` | Kept on P&L, Scorecard, Distributions — the OS picker is faster than a custom one and adapts to `color-scheme`. |
| Inline edit | Not implemented — editing still happens in modals/side forms. The Alpine helper (`window.osInlineEdit`) is in place for when a click-to-edit surface is added; that needs new single-field Livewire mutations, which were out of scope for a design-only pass. |
| Charts | P&L and Scorecard are table-first. No charting library (would need a runtime dependency). |
| Wide tables on phones | `x-table` scrolls horizontally inside its card below `sm`. Readable and contained (no page-level overflow), but a stacked card layout per row would be better for the ledger-style screens. |
| Livewire assets | Still loaded from the pinned CDN `livewire.esm.js` with `data-navigate-once`; unchanged by this pass. |

## Interaction contract (what every page now does)

1. `x-page-header` with breadcrumb, title, subtitle, optional meta chips, primary + secondary actions.
2. `x-filter-bar` with `wire:model.live.debounce.300ms` search (`data-page-search`, focused by `/`), scoped filters and a result count.
3. Skeleton on first load / filter change; `opacity-60` on the live list while a request is in flight.
4. `x-table` for tabular data (sortable headers, sticky option, numeric columns, row hover, icon actions) or `x-card` sections for detail.
5. `x-empty-state` when the query returns nothing, with the action that fixes it.
6. Toast (`$dispatch('toast')`) after every mutation; no silent saves, no full-page banners.
7. Bulk selection raises the sticky `x-bulk-bar` with a count and a clear action.
8. `⌘/Ctrl+K` command palette (jump + create), `/` search, `j/k` list nav, `a/r` in approval queues, `?` shortcut sheet.
