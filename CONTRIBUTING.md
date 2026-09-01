# Contributing to Portfolio OS

Thanks for taking the time. This document covers how to get set up, the constraints that shape the codebase, and what a mergeable pull request looks like.

By participating you agree to the [Code of Conduct](CODE_OF_CONDUCT.md).

## Local setup

Follow [docs/INSTALL.md](docs/INSTALL.md), then sign in as `admin@example.com` / `password`.

If you prefer everything running at once, `composer dev` starts the server, queue listener, log tailer and Vite together via `concurrently`.

```bash
php artisan test              # full suite (SQLite in-memory)
php artisan test --filter=Money
vendor/bin/pint               # format
vendor/bin/pint --test        # check formatting without writing
```

CI runs `pint --test`, `php artisan test` and `npm run build`. Run all three before pushing and you will not be surprised.

## Architectural constraints

These are not style preferences. Breaking one of them breaks a real deployment, so a PR that does will be asked to change regardless of how good the feature is.

### Hosting: shared hosting, PHP and MySQL only

The reference deployment is **Hostinger shared hosting over FTP**. There is no Node runtime, no Redis, no Docker, no persistent process, no root, and possibly no SSH.

- `SESSION_DRIVER`, `CACHE_STORE` and `QUEUE_CONNECTION` must work on `database` or `file`. **Never Redis.**
- Do not add a dependency that needs a persistent process, websockets, a compiled binary, or `exec()` / `proc_open()` / `symlink()` / shell access.
- No Octane, Horizon, Reverb, Sail, Pulse or Telescope in production.
- `npm` does not exist on the server. Frontend assets are built locally and uploaded.
- Queues run as a cron drip (`queue:work --stop-when-empty`), so **every queued job must be safe to run late, out of order, or twice.**
- `vendor/` has to stay installable with `--no-dev` and small enough to upload as a zip. Prefer few, small dependencies; say why in the PR if you add one.
- `composer.json` pins `config.platform.php` to the **lowest** supported PHP. Without it, `composer update` on a newer local PHP resolves packages that refuse to install on the oldest supported server. Leave the pin in place, and never raise it in the same PR as a feature.

### Money: integers, always

- Every monetary value is an **integer number of minor units** in a `bigInteger` column. Never a float, never a decimal cast to float.
- Do arithmetic through `App\Support\Money`. Read currency metadata through `App\Support\Currency` — never hardcode a currency code, symbol or exponent.
- Column names still carry historical `_paisa` / `_pkr_paisa` suffixes. They mean **minor units of the configured base currency**. Do not rename them (it breaks existing installs) and do not read a currency into them.
- **Never hard-delete a financial record.** Soft deletes only.
- **Never edit an approved distribution run.** Corrections are new adjusting entries.
- Ownership shares are per project and must total 100% for that project.
- Distributions are **manual only** — nothing auto-approves on a schedule.

### Permissions: roles are many-to-many

- Users have *many* roles. There is never a single `role` column, and never a role switcher in the UI.
- Effective permissions are the **union** of all a user's roles. Someone can be a partner and a supervisor at once, and sees one merged UI.
- Any query scoped to projects must filter by the user's project assignments **at the query level**, not in the Blade template.

### Data handling

- Timestamps are **stored in UTC** and displayed in the configured timezone via `App\Support\DisplayTimezone`. Do not call `now()` for anything user-facing that means "today" — use the helper, because attendance and monthly reporting depend on it.
- Index every foreign key, and every column used in a `where` or `orderBy`.
- **Lazy loading is disabled outside production.** A missing `with()` throws locally and in CI instead of quietly issuing a query per row. If a test or page starts failing with `LazyLoadingViolationException`, eager-load the relation — including relations your *policies* read, which is easy to miss.
- Aggregate in SQL, not in PHP, and never inside a loop over rows. Where a list needs per-row totals, add a batch helper that takes an array of IDs (see `ProfitAndLossService::monthRowsByProject()`).

### AI features

- **Never** send credential-vault rows, passwords, API keys or bank details to a provider. This is enforced in code by the sanitiser, not by convention — keep it that way.
- **Never** let a model generate SQL that gets executed. Natural-language questions map onto a fixed whitelist of read-only report methods, each of which re-applies the caller's permissions and project scope.
- The app must remain fully functional with no AI key configured.

## Code style

- **Livewire components for anything interactive.** A full page reload is a last resort.
- **Tailwind utility classes.** No new CSS files beyond the existing `app.css` and its tokens. Reuse the Blade component library in `resources/views/components/` rather than hand-rolling a table, form control or empty state — see [docs/DESIGN_AUDIT.md](docs/DESIGN_AUDIT.md) for what exists and the page pattern every screen follows.
- **Form request validation**, not inline validation in controllers.
- Follow Laravel conventions and let **Pint** settle the rest. Do not hand-format; run `vendor/bin/pint`.
- Comments should explain a constraint or a non-obvious decision. Don't narrate what the code already says.

## Tests

Pest, in `tests/Feature` (most things) and `tests/Unit` (pure logic only — note that unit tests do not boot the app, so anything touching `config()` belongs in Feature).

**Required:**

- A test for **every money calculation** — totals, allocation, FX conversion, rounding, distribution shares.
- A test for **every approval flow** — who can approve, what state it moves to, what side effects fire, and who is blocked.

Also useful to know:

- `tests/Feature/SmokeRenderTest.php` renders every parameterless page as an admin against the demo seed data. If you add a route, it is covered automatically. If you break a Blade template, this catches it.
- `tests/Feature/CurrencyTest.php` is the reference for currency-agnostic behaviour, including the fact that a **saved setting overrides `config/money.php`**.

## Migrations

**Never modify a migration that has already run in production.** Write a new one. This applies even to something as small as a column default — see `2024_01_01_000100_neutralise_currency_column_defaults.php` for the pattern.

## Pull requests

1. Branch off `main`. Name it for the change: `feature/csv-revenue-mapping`, `fix/scorecard-timezone`.
2. Keep it focused. A refactor bundled with a feature is two PRs.
3. Make sure `vendor/bin/pint --test`, `php artisan test` and `npm run build` all pass.
4. Fill in the PR template: what changed, why, how you verified it.
5. Call out anything that requires operators to act on deploy — a new migration, a new `.env` key, a `vendor/` re-upload, or rebuilt assets. This matters more than usual here, because deploys are manual FTP uploads.

Commit messages: a short imperative subject line (`Add CSV column mapping to revenue import`), and a body explaining *why* if it isn't obvious.

## Reporting bugs and requesting features

Use the [issue templates](https://github.com/tnandla/portfolio-os/issues/new/choose). For anything security-related, do **not** open a public issue — follow [SECURITY.md](SECURITY.md).
