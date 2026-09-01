# Installation

Local setup for development or evaluation. For putting it on a server, see [DEPLOYMENT.md](../DEPLOYMENT.md).

## Requirements

| | Version | Needed for |
|---|---|---|
| PHP | 8.3, 8.4 or 8.5 | The app. Standard Laravel extensions. |
| Composer | 2.x | PHP dependencies. |
| Node.js | 20.19+ or 22+ | Building CSS/JS. **Local only** — the server never runs Node. |
| Database | SQLite (default) or MySQL 8 | SQLite needs no setup. |

## Install

1. **Clone and enter the project.**

   ```bash
   git clone https://github.com/tnandla/portfolio-os.git
   cd portfolio-os
   ```

2. **Install PHP dependencies.**

   ```bash
   composer install
   ```

3. **Create the environment file and app key.**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   `APP_KEY` encrypts the credential vault. Keep it. If you lose it, every stored secret becomes unreadable.

4. **Create the database.** `.env.example` is already set to SQLite:

   ```bash
   touch database/database.sqlite
   ```

   For MySQL instead, uncomment the `DB_*` block in `.env`, create an empty database, and skip the `touch`.

5. **Migrate and seed.** This creates the schema, the five roles with their permissions, default settings, task templates, and — locally — a demo portfolio.

   ```bash
   php artisan migrate --seed
   ```

6. **Build the frontend.**

   ```bash
   npm install && npm run build
   ```

   Use `npm run dev` instead if you want hot reload while editing Blade or CSS.

7. **Serve it.**

   ```bash
   php artisan serve
   ```

   Open <http://127.0.0.1:8000>.

## Demo accounts

Seeding locally creates one account per role, so you can see how much the app changes shape depending on who signs in.

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| Partner | `partner@example.com` | `password` |
| Supervisor | `supervisor@example.com` | `password` |
| Staff | `staff@example.com` | `password` |
| Accountant | `accountant@example.com` | `password` |

> [!WARNING]
> `password` is only used when `APP_ENV` is `local` or `testing`. Anywhere else the seeder generates a random password and prints it once — copy it from the console, it is not recoverable. Set `ADMIN_PASSWORD` in `.env` to choose your own.

Demo data (fake projects, credentials, work, people, money) follows the same rule: local and testing only. On any other environment `migrate --seed` gives you roles, permissions, settings, task templates and one admin account, which is what a real installation wants. `SEED_DEMO_DATA=true|false` overrides the decision either way.

Demo records use `alpha-demo.test` / `beta-demo.test` domains and `example.com` addresses throughout.

## Configure for your organisation

Nothing is hardcoded to one country or currency.

| Setting | Where | Notes |
|---|---|---|
| Organisation name | Settings screen | Sidebar and page titles. |
| Base currency and symbol | Settings screen, or `MONEY_BASE_*` in `.env` | The saved setting wins over the env default. |
| Minor-unit exponent | `MONEY_BASE_EXPONENT` | `2` for cents, `0` for JPY/KRW, `3` for KWD/BHD. **Choose before entering data** — see the caveat in the README. |
| Second input currency | `MONEY_SOURCE_*` | For revenue paid in another currency. Set it equal to the base to run single-currency. |
| Display timezone | Settings screen, or `APP_DISPLAY_TIMEZONE` | Timestamps are stored in UTC; this is display only, and it defines "today" for attendance. |
| Late arrival hour, credential alert windows | Settings screen | |

## Tests and formatting

```bash
php artisan test            # full suite, SQLite in-memory
php artisan test --filter=Money
vendor/bin/pint             # format
vendor/bin/pint --test      # check without writing
```

The suite migrates itself and needs no database service. It does need a built frontend manifest, so run `npm run build` at least once first.

## Troubleshooting

**`Vite manifest not found`** — run `npm run build`.

**`could not find driver`** — your PHP is missing `pdo_sqlite` (or `pdo_mysql`). Install the extension and restart PHP.

**`The MAC is invalid` when revealing a credential** — `APP_KEY` changed since the secret was saved. Restore the original key; there is no recovery without it.

**Permissions look wrong after editing roles** — sign out and back in, or run `php artisan cache:clear`.
