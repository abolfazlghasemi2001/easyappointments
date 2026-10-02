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

Tooling/tests/docs: `dev/sandbox/*` (six files), `dev/sandbox/sqlite-dev-db.sh`,
`tests/Unit/Localization/*` (two files), `tests/Unit/Holidays/HolidaysModelTest.php`,
`docs/fa/step-00-audit.md`, `docs/fa/step-01-analysis.md`, `docs/fa/step-02-localization.md`,
`CUSTOMIZATIONS.md` (this file).

## Untouched by this fork

- `system/` — the whole CodeIgniter core (0 changes).
- `application/core/` — all `EA_*` classes (0 changes).

## Deployment drift (to be fixed in a later step)

The production server currently runs files that are **not** in this repository
(a custom root `Dockerfile` based on `php:8.2-fpm` and a custom `docker/nginx/nginx.conf` for
`/assets/`), while the repository still contains the upstream development `docker-compose.yml`
(10 services, hard-coded `secret`/`password` credentials). Planned in step 1 of the roadmap:

- commit an environment-driven `docker-compose.yml` (no hard-coded secrets),
- commit the production `Dockerfile` and the hardened `docker/nginx/*.conf`
  (must deny `/storage`, `/application`, `/system`, `/docs`, `/dev`, `/tests`, `/config.php`),
- add `.env.example` and an `.env` loader, and make the app fail fast when a required key is missing.

## Chromium salon UX audit — 2026-10-01

- Shared layouts (`booking_layout.php`, `account_layout.php`, `backend_layout.php`,
  `customer_portal_layout.php`): additive `barber_loading` component and `barber-motion.css`
  include, marked `FORK:`. Loading markup and motion live in new files, not the upstream layouts.
- `customer_portal_layout.php`: replace two missing Font Awesome CSS files with the existing
  JS bundles provided by gulp. The old references produced HTTP 404s and missing icons.
- `assets/js/pages/booking.js`: focus/scroll to the actual new wizard frame after user navigation,
  not the top of the landing page; synthetic auto-selection never scrolls past the hero.
- `assets/js/http/booking_http_client.js`: request generation guard prevents older available-hours
  responses from overwriting the latest date's hours.
- `assets/js/utils/http.js`: return the rejected response chain instead of reading a failed HTTP
  response body twice; preserve original status and error message for all three request helpers.
- Fork-only enhancements: opt-in tours, active-frame shortcuts, Jalali date ARIA labels,
  compact mobile header, actual Jalali grid styles, single-card layout, dark-mode calendar,
  accessible/reduced-motion loading and entrance animations.
- `dev/sandbox/server.mjs`: disable php-wasm's shared CLI cookie store for the HTTP server and
  forward separate `Set-Cookie` headers. Different browsers must not inherit each other's login/language.
- Additive tests: `tests/js/barber_experience.test.mjs`, `tests/browser/barber_smoke.mjs`.
  Playwright is a development dependency only. Local screenshots stay in ignored `storage/review/`.
- Build and audit instructions/results: `docs/fa/2026-10-01-salon-ux-audit.md`.
