# CUSTOMIZATIONS.md

This file tracks **every change this fork makes to upstream Easy!Appointments core files**, so that
future merges from `upstream` stay possible. New functionality must always live in new files
(libraries, models, helpers, hooks, controllers, views, migrations, assets) — core files are only
touched when there is no other extension point, and each such change is listed below with its reason.

- Fork: `https://github.com/abolfazlghasemi2001/easyappointments`
- Upstream: `https://github.com/alextselegidis/easyappointments` (GPL-3.0)
- Upstream remote name to add on a fresh clone: `upstream`
  ```bash
  git remote add upstream https://github.com/alextselegidis/easyappointments.git
  git fetch upstream
  ```
- Base of this fork: upstream commit `450ba15` (2026-09-09, after the v1.6.0 release)

## Rules for future work

1. Never edit anything inside `system/` (CodeIgniter core) and avoid `application/core/`.
2. Prefer the documented extension points: CI3 **hooks** (`application/config/hooks.php`),
   **config files**, new **libraries/models/helpers/controllers/views**, new **migrations**.
3. Database changes only as **new** migration files under `application/migrations/` (never modify an
   existing migration file), each with a working `down()` method.
4. Anything listed in the tables below must be kept small (ideally < 10 lines per file) and must be
   marked in the code with a comment starting with `FORK:` so that conflicts are easy to find.
   > Note: the changes from step 2 (below) predate this rule, so only `Availability.php` currently
   > carries no `FORK:` marker — it will be marked the next time that file is touched.
5. Prefer configuration over patching: if a behaviour can be toggled from the database `settings`
   table or a hook, do that instead of editing a core file.
6. `vendor/`, `node_modules/`, compiled assets (`*.min.js`, `assets/css/*.css`), `config.php`,
   `.env` and everything under `storage/*` are **never** committed (`.env.example` with empty
   placeholders is committed instead, and `.env` is listed in `.gitignore` since step 4).
7. The deployment files of step 1 (`docker-compose.prod.yml`, `deploy/*`, `scripts/*`) are additive:
   the upstream `docker-compose.yml` and `docker/nginx/nginx.conf` of the development stack are
   **not** modified, so an upstream merge cannot conflict with the production setup.
8. `deploy/nginx/default.conf` and `dev/sandbox/blocked-paths.mjs` describe the same
   "never served" paths. `tests/nginx/blocking.test.mjs` (run with `npm run test:config`) fails when
   the two lists drift apart, because a missing rule reopens a critical exposure
   (see `docs/fa/step-00-audit.md`, gap 33).

## Change log of core-file modifications

| # | Core file | Change | Reason | Step |
| --- | --- | --- | --- | --- |
| 1 | `application/config/autoload.php` | Added `'localization'` to `$autoload['helper']` (1 line) | The Persian/RTL/date helper functions are needed on every request | 2 |
| 2 | `application/config/hooks.php` | Registered the `load_localization_script_vars` hook on `post_controller_constructor` (12 lines, additive) | Exposes `calendar_type`, `persian_digits`, `display_timezone`, `text_direction`, `is_rtl` to `window.vars` without editing any controller | 2 |
| 3 | `application/libraries/Availability.php` | Load `holidays_model` in the constructor; return `[]` from `get_available_hours()` when the date is a holiday (2 additions, 6 lines) | Iranian official holidays must make a day non-bookable; there is no hook in the availability pipeline | 2 |
| 4 | `application/views/layouts/booking_layout.php` | `dir` attribute, RTL stylesheet, Persian font and Jalali script includes (13 lines) | RTL + Jalali rendering cannot be injected without editing the layout | 2 |
| 5 | `application/views/layouts/backend_layout.php` | Same as #4 (13 lines) | Same as #4 | 2 |
| 6 | `application/views/layouts/account_layout.php` | Same as #4 (11 lines) | Same as #4 | 2 |
| 7 | `application/views/components/settings_nav.php` | Added the "Holidays" menu entry (6 lines, additive) | The new holidays admin page must be reachable from the settings navigation | 2 |
| 8 | `application/views/pages/calendar.php` | Load the Persian FullCalendar locale when RTL is active (3 lines, additive) | Jalali dates inside FullCalendar views | 2 |
| 9 | `application/language/*/translations_lang.php` | 15 new keys appended to every language file (additive) | New keys for the holidays module; upstream keeps translations as flat key/value files, so a separate file is not possible | 2 |
| 10 | `assets/js/utils/date.js` | `App.Utils.Date.format()` renders Jalali dates + Persian digits when enabled (22 lines) | Central date formatting used across the whole UI | 2 |
| 11 | `assets/js/utils/ui.js` | Date/datetime pickers delegate to `App.Utils.JalaliPicker` in Jalali mode; added `getDisplayedMonth()` / `isJalaliPickerEnabled()` (134 lines changed) | flatpickr must render a Jalali grid while keeping Gregorian `Date` objects | 2 |
| 12 | `assets/js/pages/booking.js` | Detects the displayed month through `App.Utils.UI.getDisplayedMonth()` (28 lines) | The booking page request loader must work with the Jalali grid | 2 |
| 13 | `assets/js/utils/calendar_default_view.js` | Week starts on `App.Utils.Jalali.firstDayOfWeek()` (4 lines) | Saturday-first week | 2 |
| 14 | `assets/js/utils/calendar_table_view.js` | Same as #13 (2 lines) | Saturday-first week | 2 |
| 15 | `gulpfile.js`, `package.json`, `babel.config.json` | Added the `styles:rtl` task (postcss-rtlcss), the `vazirmatn` font copy, the Persian FullCalendar locale copy and replaced the abandoned `babel-preset-minify` with `gulp-terser` | Build pipeline: a single SCSS source produces both LTR and RTL stylesheets | 2 |
| 16 | `application/controllers/Booking.php` | `register()` stores the appointment through the new `booking_service` (4 lines + `FORK:` comment) | **Explicitly requested** (step 5): the public booking path never called `has_provider_conflict()`, so two simultaneous requests could both create an appointment for the same slot. There is no hook or model extension point inside the controller | 5 |
| 17 | `application/config/autoload.php` | Added the `'phone'` helper to `$autoload['helper']` (1 line + `FORK:` comment) | Iranian phone normalization/validation is needed by the booking flow, the waitlist model and (later) the OTP service | 5 |
| 18 | `application/libraries/Availability.php` | Ignore appointments that do not block their slot anymore (cancelled or expired hold) in `get_available_periods()` (a library of its own class, 8 lines + `FORK:` comment) | Without it a released slot is still reported as busy, so the hours offered to the customer would differ from the hours that can be booked | 5 |

### Additive files (no upstream file was modified)

Application code: `application/libraries/Jalali_date.php`, `application/helpers/localization_helper.php`,
`application/hooks/localization.php`, `application/models/Holidays_model.php`,
`application/controllers/Holidays.php`, `application/views/pages/holidays.php`,
`application/data/iran_holidays.php`, `application/migrations/070_create_holidays_table.php`,
`application/migrations/071_add_localization_settings.php`,
`application/config/development/database.php`, `application/config/testing/database.php`.

Frontend: `assets/css/persian.scss`, `assets/js/utils/jalali_date.js`,
`assets/js/utils/jalali_picker.js`, `assets/js/http/holidays_http_client.js`,
`assets/js/pages/holidays.js`.

Step 5: `application/libraries/Booking_service.php`, `application/libraries/Appointment_status.php`,
`application/controllers/Booking_maintenance.php`, `application/helpers/phone_helper.php`,
`application/models/Waitlist_model.php`, `application/migrations/072_add_booking_integrity.php`,
`application/migrations/073_create_waitlist_table.php`, `tests/Unit/Booking/*` (three files),
`tests/Unit/Helper/PhoneHelperTest.php`, `docs/fa/step-05-booking.md`.

Steps 3 and 4: `application/migrations/074_create_otp_codes_table.php`,
`application/migrations/075_create_sms_messages_table.php`, `application/libraries/Otp_service.php`,
`application/libraries/Sms_client.php`, `application/libraries/Sms_provider_interface.php`,
`application/libraries/Sms_provider_textbee.php`, `application/libraries/Sms_provider_mock.php`,
`application/controllers/Customer.php`, `application/controllers/Sms_maintenance.php`,
`application/views/layouts/customer_portal_layout.php`, `application/views/pages/customer_portal.php`,
`assets/js/http/customer_portal_http_client.js`, `assets/js/pages/customer_portal.js`,
`assets/css/customer_portal.scss`, `application/language/{persian,english}/customer_portal_lang.php`,
`tests/Unit/Sms/*` (three files), `.env.example`, `docs/fa/step-03-otp.md`, `docs/fa/step-04-textbee.md`.

Step 1: `config-loader.php`, `docker-compose.prod.yml`, `docker-compose.caddy.yml`,
`docker/php-fpm/Dockerfile.prod`, `docker/php-fpm/prod-entrypoint.sh`,
`docker/php-fpm/php-prod.ini`, `docker/php-fpm/php-fpm-prod.conf`, `deploy/nginx/default.conf`,
`deploy/Caddyfile`, `deploy/mysql/prod.cnf`, `deploy/systemd/ea-compose.service`,
`application/controllers/Health.php`, `scripts/{deploy,backup,restore,rollback,server-hardening,demo-prod-stack}.sh`,
`scripts/lib/env.sh`, `dev/sandbox/blocked-paths.mjs`, `tests/nginx/blocking.test.mjs`,
`docs/fa/step-01-production.md`.

Tooling/tests/docs: `dev/sandbox/*` (six files), `dev/sandbox/sqlite-dev-db.sh`,
`tests/Unit/Localization/*` (two files), `tests/Unit/Holidays/HolidaysModelTest.php`,
`docs/fa/step-00-audit.md`, `docs/fa/step-01-analysis.md`, `docs/fa/step-02-localization.md`,
`CUSTOMIZATIONS.md` (this file).

## Untouched by this fork

- `system/` — the whole CodeIgniter core (0 changes).
- `application/core/` — all `EA_*` classes (0 changes).

## Deployment drift (resolved in step 1)

The production server used to run files that were **not** in this repository (a custom root
`Dockerfile` based on `php:8.2-fpm` and a custom `docker/nginx/nginx.conf` for `/assets/`), while
the repository only contained the upstream development `docker-compose.yml` (10 services with
hard-coded `secret`/`password` credentials). Step 1 removed that drift:

| Before (server only) | Now (in the repository) |
| --- | --- |
| root `Dockerfile` (php:8.2-fpm, not committed) | `docker/php-fpm/Dockerfile.prod` |
| unknown compose file with 3-4 services | `docker-compose.prod.yml` (db + app + web, no hard-coded secret) |
| custom nginx config "for /assets/" | `deploy/nginx/default.conf` (denies `/storage`, `/application`, `/system`, `/docs`, `/dev`, `/tests`, `/config.php`, every other `.php`) |
| credentials inside `config.php` on the server | `.env` (never committed) + `config-loader.php` + `.env.example` |
| no TLS | `deploy/Caddyfile` + `docker-compose.caddy.yml` (Let's Encrypt) |
| no backup/restore procedure | `scripts/backup.sh`, `scripts/restore.sh`, `scripts/rollback.sh` |
| no health endpoint | `application/controllers/Health.php` (`/index.php/health`) |

The upstream `docker-compose.yml` stays untouched for the development stack; the production stack is
a separate file, so a future `git merge upstream/main` cannot break the deployment.

The old `config.php` of an existing installation keeps working: `config-loader.php` only defines the
`Config` class when the file did not define it already, so an installation can keep its classic
`config.php` and use `.env` only for the optional keys (TextBee, cron keys, backup directory).
