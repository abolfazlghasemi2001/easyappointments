# گزارش مرحله ۰ — ممیزی و خط پایه (Baseline Audit)

> سامانه نوبت‌دهی آرایشگاه مردانه — فورک `abolfazlghasemi2001/easyappointments` از Easy!Appointments (GPL-3.0)
> تاریخ گزارش: ۱۴۰۵/۰۷/۰۸ (2026-09-30) — دونده کاری: شاخه `arena/01a0f3a4-easyappointments`
> کامیت مبنا: `0f83e6ee2ff277fa696691f42051d54fbcab0d45` (ادغام PR #1 — بومی‌سازی فارسی)
> **معیار پذیرش مرحله ۰:** گزارش کامل، **بدون هیچ تغییر کدی**. ✅ هیچ فایل PHP/JS/SCSS/config در این مرحله تغییر نکرد
> (تنها دو فایل مستندات مارک‌داون اضافه شد — بخش ۹).

---

## بخش ۰ — خلاصه اجرایی و یک مانع جدی

### ۰-۱) مانع: دسترسی به سرور تولید از این محیط وجود ندارد

طبق دستور شما «wizard نصب روی سرور باید چک شود»، اما **این محیط اجرا به سرور `91.247.171.117` دسترسی ندارد.** این را
تست کردم و نتیجه قطعی است:

| آزمون | فرمان | نتیجه |
| --- | --- | --- |
| باز بودن پورت SSH (8080) | `nc -zv 91.247.171.117 8080` | TCP handshake موفق (پورت باز است) |
| ارسال درخواست HTTP روی همان سوکت | `socket.sendall(b'GET / HTTP/1.0\r\n\r\n')` | `Connection reset by peer` |
| باز بودن پورت 9090 | `nc -zv 91.247.171.117 9090` | TCP handshake موفق |
| درخواست HTTP به 9090 | `curl -sSI http://91.247.171.117:9090/` | `Recv failure: Connection reset by peer` |
| خروج عمومی به اینترنت روی ۴۴۳ | `curl https://example.com` | `SSL_ERROR_SYSCALL` (بسته/بلاک است) |
| وجود کلید SSH یا `~/.ssh/config` | `ls ~/.ssh/` | وجود ندارد |

جمع‌بندی: محیط سندباکس فقط به چند دامنه خاص (GitHub، npm، PyPI) اجازه خروج دارد؛ اتصال به سرور شما در لایه شبکه/فایروال
سندباکس بلاک شده است. همچنین سندباکس پس از جلسه قبل بازنشانی شده است (`/home/user/.devtools` و runtime شبیه‌ساز PHP
پاک شده‌اند؛ `vendor/`، `node_modules/`، `config.php` و فایل‌های `*.min.*` هم در این checkout نیستند چون همه در
`.gitignore` هستند و روی سرور ساخته می‌شوند).

**پیامد:** هرچه مربوط به «وضعیت واقعی سرور» است (نصب wizard، پورت‌های باز، رمز پیش‌فرض، دسترسی وب به `storage/`،
نسخه PHP/MySQL کانتینرها، بکاپ) در این گزارش با علامت **«⛔ تأییدنشده — نیازمند سرور»** مشخص شده است. من هیچ‌کدام را
حدس نمی‌زنم. برای رفع این مانع دو راه دارید که در بخش ۱۰ آمده است (کلید SSH یا اجرای یک اسکریپت فقط-خواندنی).

### ۰-۲) وضعیت کد در یک نگاه

| موضوع | وضعیت واقعی |
| --- | --- |
| نسخه برنامه | `application/config/app.php` → `$config['version'] = '1.6.0'` |
| نسخه در `composer.json` | `1.5.2` — **ناهمخوانی در خود upstream**، نه اشتباه فورک |
| آخرین نسخه در `CHANGELOG.md` | `[1.6.0] - 2026-05-27` |
| کامیت مبنا نسبت به upstream | `450ba15` (۲۰۲۶-۰۹-۰۹؛ فقط به‌روزرسانی وابستگی‌های dependabot بعد از انتشار ۱.۶.۰) |
| فریم‌ورک | CodeIgniter 3 (هسته سفارشی با پیشوند `EA_`: ۱۷ کلاس در `system/core` + ۲۶ کلاس در `application/core`) |
| PHP موردنیاز | `>=8.2` (`composer.json`)؛ سرور شما PHP 8.2 (طبق اطلاعات شما) |
| دیتابیس | MySQL/MariaDB، درایور `mysqli`، پیشوند جدول `ea_`؛ ۱۴ جدول + جدول `ci_sessions` استفاده‌نشده |
| زبان‌ها | ۴۱ زبان؛ **فارسی کامل: ۵۸۹ کلید = ۵۸۹ کلید انگلیسی، صفر کلید گمشده و صفر مقدار خالی** |
| بومی‌سازی گام ۲ | انجام شده (تقویم جلالی، RTL، تعطیلات ایران، ارقام فارسی، وزیرمتن) |
| مهاجرت‌ها | ۷۱ فایل؛ آخرین: `071_add_localization_settings.php` |
| تست‌ها | ۶ فایل در `tests/` (شامل `TestCase.php`)؛ ۳۶ تست / ۷۴۰۰ assertion برای فورک + ۲ تست قدیمی هسته |
| REST API | ۱۳ کنترلر `api/v1`، ۲۵ مسیر در `openapi.yml`، احراز هویت Bearer/Basic |
| پیامک | **صفر** زیرساخت — نه سرویس، نه صف، نه قالب |
| پرداخت | **صفر** زیرساخت |
| OTP | **صفر** زیرساخت |
| `.env` | **وجود ندارد** — کلید `env()` فقط `$_ENV` را می‌خواند و در php-fpm عملاً خالی است (رجوع به ۶-۱-۲) |

---

## بخش ۱ — شناسنامه فنی

| مورد | مقدار | مدرک |
| --- | --- | --- |
| نام/نسخه | Easy!Appointments 1.6.0 (فورک فارسی) | `application/config/app.php:12` |
| مجوز | GPL-3.0 | `LICENSE`، `composer.json` |
| مالکیت فکری | حفظ شده؛ نام و لینک اصلی پروژه در سربرگ همه فایل‌ها | سربرگ فایل‌ها |
| فریم‌ورک | CodeIgniter 3.1.x تغییر‌یافته (`EA_` prefix) | `application/config/config.php` → `subclass_prefix = 'EA_'` |
| الگوی معماری | MVC + Libraries + Helpers + Hooks |
| PHP | حداقل ۸.۲؛ تصویر توسعه در مخزن `php:8.5-fpm` است (`docker/php-fpm/Dockerfile`) — با تصویر تولید سرور (`php:8.2-fpm`) یکی نیست |
| اکستنشن‌های لازم PHP | `curl, json, mbstring, gd, simplexml, fileinfo` (`composer.json`) + `intl, ldap, pdo_mysql, soap, sockets, zip, exif, sqlite3, bcmath, redis` (تصویر توسعه) |
| وابستگی‌های Composer (اصلی) | `phpmailer`، `monolog`، `guzzle`، `google/apiclient`، `sabre/vobject`، `jsvrcek/ics`، `ezyang/htmlpurifier`، `gregwar/captcha`، `altcha-org/altcha`، `symfony/finder` |
| وابستگی‌های npm | `jquery`، `bootstrap 5`، `fullcalendar 6`، `flatpickr`، `select2`، `tippy`، `trumbowyg`، `moment`، `vazirmatn` |
| فرانت‌اند | jQuery + Bootstrap 5 (بدون فریم‌ورک SPA)، اسکریپت‌ها در `assets/js` (۸۷ فایل JS) |
| تست | PHPUnit 12 با bootstrap روی `index.php` (`phpunit.xml`) |
| CI | `.github/workflows/ci.yml` روی PHP 8.3، اجرای PHPUnit |

---

## بخش ۲ — ساختار پروژه و نقاط توسعه

### ۲-۱) ساختار پوشه‌ها (آمار دقیق)

| مسیر | محتوا | تعداد |
| --- | --- | --- |
| `index.php` | بوت‌استرپ: بارگذاری `config.php`، `vendor/autoload.php`، تعیین `ENVIRONMENT` | ۱ فایل |
| `config-sample.php` | نمونهٔ تنظیمات ریشه (کلاس `Config`) — `config.php` واقعی در `.gitignore` است | ۱ |
| `application/controllers` | کنترلرهای وب | ۴۴ |
| `application/controllers/api/v1` | کنترلرهای REST | ۱۳ |
| `application/models` | مدل‌ها | ۱۶ |
| `application/libraries` | کتابخانه‌ها | ۱۸ |
| `application/helpers` | هلپرها | ۲۰ |
| `application/views` | قالب‌ها و صفحات | ۷۹ |
| `application/migrations` | مهاجرت‌ها | ۷۱ |
| `application/language` | ۴۱ زبان، هر کدام `translations_lang.php` | ۴۱ پوشه |
| `application/config` | تنظیمات CI3 + `app.php`, `google.php`, `email.php`, `development/database.php`, `testing/database.php`, `testing/routes.php` | ۱۶ فایل |
| `application/data` | داده ثابت: `iran_holidays.php` | ۱ |
| `application/hooks` | `security_headers.php`, `localization.php`, `storage_cleanup.php` | ۳ |
| `system/` | هسته CodeIgniter (تغییرنیافته) | ۱۷ کلاس core |
| `assets/` | `css` (SCSS منبع + خروجی ساخته‌شده)، `js` (۸۷ فایل)، `img` | — |
| `storage/` | `cache`, `logs`, `sessions`, `backups`, `uploads` (هر کدام `.htaccess` + `index.html`) | ۵ زیرپوشه |
| `docker/` | `nginx/nginx.conf`, `php-fpm/{Dockerfile,php-ini-overrides.ini,start-container}` | ۴ فایل |
| `docs/` | مستندات انگلیسی + `docs/fa/` (گزارش‌های فارسی) | ۱۷ فایل |
| `tests/` | `Unit/Helper`, `Unit/Localization`, `Unit/Holidays` | ۶ فایل |
| `dev/sandbox/` | ابزار اجرای محلی بدون PHP/Docker (`run.mjs`, `server.mjs`, `minicomposer.py`, `sqlite-dev-db.sh`, `setup.sh`) | ۶ فایل |

**جمع کل PHP در مخزن:** ۱۰۵۰ فایل.

### ۲-۲) چرخه درخواست و نقاط توسعه (Extension Points)

1. **هوک‌ها** — مکانیزم رسمی CI3، فعال (`$config['enable_hooks'] = true`). فایل `application/config/hooks.php`:
   - `post_controller_constructor` → `add_security_headers()` (`application/hooks/security_headers.php`) — هدرهای امنیتی + HSTS شرطی.
   - `post_controller_constructor` → `load_localization_script_vars()` (`application/hooks/localization.php`) — افزودهٔ فورک؛ تزریق `calendar_type`, `persian_digits`, `display_timezone`, `text_direction`, `is_rtl` به `window.vars`.
   - `post_system` → `storage_cleanup()` (`application/hooks/storage_cleanup.php`) — پاک‌سازی احتمالی (۱٪ درخواست‌ها).
   > **نکته مهم برای مراحل بعد:** CI3 فقط `pre_system`, `pre_controller`, `post_controller_constructor`, `post_controller`, `display_override`, `cache_override`, `post_system` را می‌شناسد. برای «audit log»، «صف پیامک» و «rate limit» کافی است هوک جدید در همین فایل ثبت شود؛ **نیازی به تغییر کنترلرها نیست**.
2. **کلاس پایه کنترلر** — `application/core/EA_Controller.php` (۲۱۱ خط): آماده‌سازی زبان، منطقه زمانی، بررسی `ensure_user_exists()`، بارگذاری متغیرهای مشترک HTML/JS، بررسی قابل‌نوشتن بودن `storage/`. این کلاس نقطه توسعه رسمی پروژه است.
3. **مدل پایه** — `application/core/EA_Model.php` (الگوی `save/find/get/search/only/validate` در همه مدل‌ها یکسان است؛ افزودن مدل جدید کم‌ریسک است).
4. **هلپرهای سراسری** — به‌صورت autoload در `application/config/autoload.php`: ۲۳ هلپر شامل `setting`, `permission`, `rate_limit`, `security`, `http`, `env`, `localization` (فورک).
5. **مسیرها** — `application/config/routes.php` (۲۱۲ خط) + تابع `route_api_resource()` در `application/helpers/routes_helper.php`. بخش `CUSTOM ROUTING` در انتهای فایل صراحتاً برای مسیرهای ما رزرو شده است.
6. **رویداد (Event) واقعی** — CI3 رویداد داخلی ندارد؛ معادل‌های موجود: هوک‌ها + **وبهوک‌ها** (بخش ۲-۵) + `Synchronization` library.
7. **صف/Job** — وجود ندارد. `storage_cleanup` تنها فرایند زمان‌بندی‌شده است. برای یادآور پیامکی و retry باید صف ساده (جدول + کرون) خودمان را بسازیم.
8. **کنسول** — `application/controllers/Console.php` با دستورهای `install`, `migrate [up|down|fresh]`, `seed`, `backup`, `sync`, `cleanup`, `help` (اجرا: `php index.php console <cmd>`). نقطه ورود رسمی برای کرون و اسکریپت‌های ما.

### ۲-۳) کتابخانه‌های کلیدی

| کتابخانه | نقش |
| --- | --- |
| `Accounts` | ورود/خروج، هش رمز، توکن بازیابی رمز |
| `Availability` | **موتور محاسبه زمان‌های آزاد** (۶۵۳ خط) |
| `Notifications` | ارسال ایمیل رزرو/تغییر/حذف (۳۵۸ خط) — **تنها کانال اعلان** |
| `Email_messages` | تولید بدنه پیام‌های ایمیل |
| `Permissions` | بررسی سطح دسترسی بر پایه نقش (`PRIV_*`) |
| `Synchronization` | ارکستراسیون همگام‌سازی با تقویم‌های خارجی |
| `Google_sync`, `Caldav_sync`, `Ics_file` | تقویم گوگل (OAuth)، CalDAV، خروجی ICS |
| `Webhooks_client` | فراخوانی وبهوک‌ها با هدر مخفی (`secret_header`) |
| `Jitsi_client` | ساخت لینک جلسه آنلاین |
| `Altcha_client` | کپچا جایگزین |
| `Jalali_date` (فورک) | تبدیل و قالب‌بندی تقویم جلالی |
| `Cleanup`, `Instance`, `Api`, `Timezones`, `Ldap_client` | پاک‌سازی دوره‌ای، نصب/مهاجرت/seed/backup، هسته API، مناطق زمانی، LDAP |

### ۲-۴) REST API

- ۱۳ کنترلر در `application/controllers/api/v1/`: `appointments`, `admins`, `customers`, `providers`, `secretaries`, `services`, `service_categories`, `unavailabilities`, `webhooks`, `blocked_periods`, `working_plan_exceptions`, `settings`, `availabilities`.
- احراز هویت (`application/libraries/Api.php::auth()`): توکن Bearer با مقایسه `hash_equals`، یا Basic Auth که **فقط نقش `admin` را می‌پذیرد**؛ در غیر این صورت پاسخ ۴۰۱.
- CSRF برای مسیرهای `api/v1/.*` غیرفعال است (طبیعی برای API توکنی).
- مشخصات کامل در `openapi.yml` (۷۹KB، ۲۵ مسیر، OpenAPI 3.0.3). Swagger UI در `docker-compose.yml` توسعه هست (پورت ۸۰۰۰) ولی **روی سرور تولید لازم نیست و نباید باز باشد**.
- **شکاف:** API برای نقش «آرایشگر» (provider) سخت‌سازی نشده؛ `Appointments_api_v1` همه نوبت‌ها را برمی‌گرداند (فیلتر سمت سرور بر اساس provider وجود ندارد). در ممیزی IDOR مرحله بعد باید بازبینی شود.

### ۲-۵) وبهوک‌ها

`application/config/constants.php:138-155` — ۱۸ رویداد: `appointment_save/delete`, `unavailability_save/delete`, `customer_save/delete`, `service_save/delete`, `service_category_save/delete`, `provider_save/delete`, `secretary_save/delete`, `admin_save/delete`, `blocked_period_save/delete`.
جدول `ea_webhooks` + ستون `secret_header` (migration 060). **این کانال آماده، بهترین نقطه اتصال «پیامک» بدون دست‌زدن به هسته نیست** (چون وبهوک، HTTP خارجی است) اما برای «اطلاع‌رسانی به سیستم‌های دیگر» عالی است.

### ۲-۶) سیستم اعلان (وضعیت فعلی)

- **فقط ایمیل.** `Notifications::notify_appointment_saved()` و `notify_appointment_deleted()` → `Email_messages` → `EA_Email` (PHPMailer).
- پیکربندی: `application/config/email.php` (پیش‌فرض `protocol = 'mail'`) + تنظیمات SMTP داخل جدول تنظیمات (قابل تغییر از پنل).
- **بدون صف، بدون retry، بدون backoff** — ارسال در همان درخواست وب انجام می‌شود. برای پیامک باید صف بسازیم.
- **هیچ زیرساخت SMS/OTP/پوش وجود ندارد** (تأیید شده با جست‌وجوی کل مخزن).

### ۲-۷) منطق اسلات‌ها و رزرو (شرح دقیق وضعیت فعلی)

**موتور:** `application/libraries/Availability.php::get_available_hours($date, $service, $provider, $exclude_appointment_id)`

ترتیب تصمیم‌گیری دقیقاً این است:

1. اگر کل روز «مسدود» باشد (`blocked_periods_model->is_entire_date_blocked`) → آرایه خالی. *(در فورک، تعطیلات رسمی هم به این شرط اضافه شده است.)*
2. اگر روز تعطیل باشد (`holidays_model->is_holiday`) → آرایه خالی. **(افزودهٔ فورک، گام ۲)**
3. اگر خدمت `attendants_number > 1` باشد (خدمت گروهی) → `consider_multiple_attendants()`؛ وگرنه:
   - `get_available_periods()`: پلن کاری هفتگی آرایشگر − استراحت‌ها (`breaks`) − عدم‌دسترسی‌ها (`unavailabilities`) − استثناهای پلن (`working_plan_exceptions`) − نوبت‌های موجود. *(نوبت‌ها با احتساب `duration` خدمت و `exclude_appointment_id` حذف می‌شوند.)*
   - `generate_available_hours()`: از هر بازه خالی، ساعت‌ها با گام `service.slot_interval` (پیش‌فرض ۱۵ دقیقه) تولید می‌شوند، به شرطی که `duration` خدمت تا انتهای بازه جا شود.
4. `consider_book_advance_timeout()`: حذف ساعت‌هایی که کمتر از `book_advance_timeout` دقیقه (تنظیم سیستم، پیش‌فرض ۰) با «الان» فاصله دارند — محاسبه در منطقه زمانی آرایشگر.
5. `consider_future_booking_limit()`: اگر تاریخ از `future_booking_limit` روز (پیش‌فرض ۹۰) جلوتر باشد → آرایه خالی.

**مسیر رزرو عمومی:** `Booking::index()` (رندر) → `Booking::register()` (POST، JSON) →
`check_datetime_availability()` → ذخیره مشتری → `appointments_model->save()` → `Synchronization` → `Notifications` → `Webhooks_client`.

**«هرکدام» (Any Provider):** `id_users_provider === ANY_PROVIDER` (`any-provider`) → `search_any_provider()` آرایشگری را برمی‌گرداند که بیشترین ساعت آزاد را دارد و در ساعت انتخابی آزاد است.

### ۲-۸) ریسک‌های اثبات‌شده در منطق رزرو (ورودی گام ۴)

| # | ریسک | مدرک | اثر |
| --- | --- | --- | --- |
| ۱ | **رزرو هم‌زمان (Race Condition)** — مسیر عمومی رزرو `Appointments_model::has_provider_conflict()` را صدا نمی‌زند و هیچ تراکنش/قفل/قید یکتای دیتابیسی نیست | `application/controllers/Booking.php:603` (`check_datetime_availability`) در مقابل `application/models/Appointments_model.php:724` که فقط در `Calendar.php:332` استفاده شده؛ جدول `appointments` در `001_specific_calendar_sync.php` هیچ `unique` ندارد | دو نوبت روی یک ساعت برای یک آرایشگر |
| ۲ | چند خدمت در یک نوبت پشتیبانی نمی‌شود | یک ستون `id_services` در جدول `appointments` | نیاز به جدول میانی `appointment_services` |
| ۳ | بدون buffer بین نوبت‌ها | جست‌وجوی کل مخزن: مفهوم buffer وجود ندارد | نوبت‌های چسبیده، خستگی آرایشگر |
| ۴ | بدون hold رزرو | — | کاربر می‌تواند اسلات را در حین پرداخت از دست بدهد |
| ۵ | بدون State Machine | ستون `status` متن‌آزاد با مقادیر `["Booked","Confirmed","Rescheduled","Cancelled","Draft"]` (`migration 043`) و بدون هیچ قاعده گذار | وضعیت‌های متناقض، گزارش‌های نادرست |
| ۶ | بدون no-show / بلاک مشتری / شمارش تخلف | — | ضرر مالی مستقیم برای آرایشگاه |
| ۷ | محدودیت پیش‌رزرو فقط روزشمار است، نه تنظیم زمانی ساعتی | `consider_future_booking_limit` | کافی است |

---

## بخش ۳ — سیستم ترجمه و وضعیت فارسی/RTL

| مورد | وضعیت | مدرک |
| --- | --- | --- |
| مکانیزم ترجمه | فایل‌های `application/language/<lang>/translations_lang.php` با `$lang['key'] = 'value';` و بارگذاری از `EA_Lang` | `application/language/*/` |
| کد زبان فارسی | `'fa' => 'persian'` و در `available_languages` | `application/config/config.php` |
| پوشش فارسی | ۵۸۹ کلید فارسی = ۵۸۹ کلید انگلیسی، **صفر کلید گمشده، صفر مقدار خالی** | شمارش مستقیم `grep -c "^\\$lang\\["`: ۵۸۹ در هر دو فایل |
| زبان پیش‌فرض | از `Config::LANGUAGE` در `config.php` ریشه خوانده می‌شود؛ در `migration 071` تنظیم `default_language=persian` درج شده است | `application/migrations/071_add_localization_settings.php` |
| RTL | `dir="rtl"` در سه layout، بارگذاری نسخه RTL استایل‌ها (تولید خودکار با `postcss-rtlcss` در `gulpfile.js`)، `assets/css/persian.scss` | گام ۲ |
| تقویم جلالی | کتابخانه PHP (`application/libraries/Jalali_date.php`) + معادل JS (`assets/js/utils/jalali_date.js`) + picker (`assets/js/utils/jalali_picker.js`)؛ **ذخیره‌سازی هم‌چنان میلادی/UTC** | گام ۲ |
| ارقام فارسی و قلم | `localization_helper.php` (`localize_number`, `persian_digits_enabled`) + قلم وزیرمتن خودمیزبان (OFL) | گام ۲ |
| هفته از شنبه | `first_weekday=saturday` + `App.Utils.Jalali.firstDayOfWeek()` | گام ۲ |
| تعطیلات ایران | جدول `ea_holidays` + مدل + کنترلر + صفحه تنظیمات + درون‌ریزی رسمی (`application/data/iran_holidays.php`) | گام ۲ |

**شکاف‌های باقی‌مانده در بومی‌سازی:** (الف) برای هر ماژول جدیدی که در مراحل بعد می‌سازیم باید کلید فارسی اضافه شود؛
(ب) متن رابط کاربری ماژول‌های آینده باید بازبینی شود؛ (پ) قالب ایمیل‌های فعلی ترجمه فارسی دارند اما «قالب پیامک فارسی»
وجود ندارد؛ (ت) ارزیابی چشمی نهایی RTL روی مرورگر واقعی هنوز توسط شما انجام نشده است.

---

## بخش ۴ — ممیزی امنیتی

### ۴-۱) موارد قابل اثبات از مخزن (بدون نیاز به سرور)

| شدت | مورد | جزئیات فنی | مسیر |
| --- | --- | --- | --- |
| **بحرانی** | **احتمال افشای `storage/` و `config.php` از وب** | در `docker/nginx/nginx.conf` هیچ قاعده‌ای برای بلاک‌کردن `/storage`, `/application`, `/docs`, `/dev`, `/tests` وجود ندارد. `try_files $uri $uri/ /index.php?$args` هر فایل موجود را سرو می‌کند و `location ~ ^.+.php` هر مسیر `.php` را **اجرا** می‌کند. اگر کانفیگ سفارشی روی سرور هم همین‌طور باشد: `/storage/backups/*.gz` (بکاپ کامل دیتابیس با داده مشتریان و هش رمز)، `/storage/sessions/*` (فایل نشست‌ها → **ربایش حساب**)، `/config.php`، `/docs/*` قابل دسترس‌اند | `docker/nginx/nginx.conf` |
| **بالا** | **هیچ `.htaccess` در ریشه نیست** | محافظت‌های `.htaccess` فقط در `application/`, `system/`, و ۴ پوشه `storage/` هستند و **فقط روی Apache** اثر دارند | `find . -name .htaccess` |
| **بالا** | **بدون HTTPS** | پورت 9090 → HTTP خام. رمز ورود، کوکی نشست (`sess_cookie_name=ea_session`) و داده نوبت‌ها بدون رمزنگاری منتقل می‌شود. `cookie_secure` از `base_url` مشتق می‌شود و اکنون `false` است | `application/config/config.php` |
| **بالا** | **`.env` وجود ندارد؛ مکانیزم secret مبتنی بر `$_ENV` کار نمی‌کند** | `env()` فقط `$_ENV[$key]` را می‌خواند. در php-fpm پیش‌فرض (`variables_order = GPCS`) آرایه `$_ENV` خالی است → هر `env('X')` بی‌صدا مقدار پیش‌فرض برمی‌گرداند. یعنی رمزها ناچاراً در `config.php` می‌مانند (این فایل در `.gitignore` است ✔). الزام شما (قاعده ۱۰/۱۱) بدون افزودن یک loader `.env` محقق نمی‌شود | `application/helpers/env_helper.php` |
| **بالا** | `db_debug = TRUE` در محیط production | خطای SQL باعث نمایش کوئری و ساختار جداول می‌شود | `application/config/database.php` |
| **متوسط** | CSRF مسیرهای عمومی رزرو | `csrf_exclude_uris = ['api/v1/.*','booking/.*','booking_cancellation/.*','booking_confirmation/.*']`. برای جبران، `Booking::verify_csrf_token()` کوکی را با توکن ارسالی مقایسه می‌کند (`hash_equals`) — یک double-submit ساده و بدون ذخیره سمت سرور | `application/config/config.php`, `application/controllers/Booking.php:90` |
| **متوسط** | کلید رمزنگاری مشتق‌شده در نبود `ENCRYPTION_KEY` | `hash('sha256', APPPATH . DB_PASSWORD . php_uname(), true)` — قابل بازتولید؛ باید `define('ENCRYPTION_KEY', ...)` در `config.php` سرور تعریف شود | `application/config/config.php` |
| **متوسط** | رمز پیش‌فرض در seed | `console seed` کاربر `administrator` با رمز `administrator` می‌سازد | `application/libraries/Instance.php:93` |
| **متوسط** | نبود محدودیت نرخ روی مسیرهای عمومی کلیدی | `rate_limit()` روی `Login`, `Privacy`, `Booking_cancellation` اعمال شده؛ اما مسیرهای زیر **بدون محدودیت**اند: `booking/register`, `booking/get_available_hours`, `booking/get_unavailable_dates` و کل `api/v1/*` | `application/helpers/rate_limit_helper.php` |
| **متوسط** | پذیرش Cookbook: `sess_match_ip = true` | برای کاربران موبایل ایران (تغییر IP بین سلول‌ها/Wi-Fi) باعث خروج‌های ناگهانی می‌شود؛ همچنین `proxy_ips` خالی است | `application/config/config.php` |
| **پایین** | CORS پیش‌فرض باز برای «همه Origin»ها وقتی `CORS_ALLOWED_ORIGINS` تعریف نشده باشد | در `routes.php` وقتی این ثابت تعریف نشده باشد، هر Origin ای منعکس می‌شود | `application/config/routes.php` |
| **پایین** | `/dev/sandbox/*` و `/docs/*` داخل webroot | افشای ابزار توسعه و مستندات معماری | ساختار مخزن |

**موارد مثبت (تأیید شده):** CSRF سراسری فعال · هدرهای امنیتی پایه در هوک · ورود با `password_verify` · هش توکن‌ها ·
`hash_equals` برای API token · `permitted_uri_chars` محدود · `sanitize_filename` و `validate_id`/`validate_hash` در
`security_helper.php` · محافظت `defined('BASEPATH')` در همه فایل‌های غیرعمومی · `config.php` و `vendor/` و
`storage/*` در `.gitignore` · هیچ کلید/رمزی در مخزن commit نشده است (بررسی شد).

### ۴-۲) موارد نیازمند تأیید روی سرور ⛔ (چک‌لیست فقط-خواندنی)

> این دستورها **هیچ تغییری ایجاد نمی‌کنند** (همه read-only). طبق قاعده ۱۲، پیش از اجرای هر دستور `docker` یا
> `systemctl` روی سرور از شما تأیید می‌گیرم. اگر دسترسی بدهید، خودم اجرا و گزارش می‌کنم:

```bash
# ۱) این فایل را در مرورگر باز کنید (دامنه/آی‌پی خودتان) — هیچ‌کدام نباید دانلود شوند:
#    http://91.247.171.117:9090/config.php
#    http://91.247.171.117:9090/storage/backups/
#    http://91.247.171.117:9090/storage/logs/
#    http://91.247.171.117:9090/storage/sessions/
#    http://91.247.171.117:9090/application/config/config.php
#    http://91.247.171.117:9090/docs/readme.md
#    http://91.247.171.117:9090/dev/sandbox/setup.sh
# انتظار: 403/404 برای همه. اگر ۲۰۰ یا دانلود شد → یافته بحرانی.

# ۲) کانفیگ واقعی nginx روی سرور (خواندن فایل داخل کانتینر، بدون تغییر):
docker exec $(basename /var/www/easyappointments)-nginx-1 cat /etc/nginx/conf.d/default.conf

# ۳) وضعیت نصب برنامه:
docker exec <php-fpm-container> php index.php console help
ls -la /var/www/easyappointments/config.php
grep -n "DEBUG_MODE\|BASE_URL\|LANGUAGE\|ENCRYPTION_KEY\|CORS_ALLOWED_ORIGINS" /var/www/easyappointments/config.php

# ۴) وضعیت نسخه‌ها و پورت‌های باز (بدون تغییر):
docker ps --format 'table {{.Names}}\t{{.Ports}}\t{{.Status}}'
sudo ss -tlnp
php -v   # داخل کانتینر php-fpm: docker exec <php-fpm> php -v
mysql --version

# ۵) پورت‌های باز از بیرون (روی سیستم خودتان):
nmap -Pn -p- 91.247.171.117        # یا: nc -zv 91.247.171.117 1-65535

# ۶) وضعیت فایروال/swap/بکاپ:
sudo ufw status verbose
free -h
ls -la /var/www/easyappointments/storage/backups/

# ۷) آخرین مهاجرت اجراشده:
docker exec <container> php index.php console migrate   # خروجی باید «nothing to migrate» بدهد
```

### ۴-۳) وضعیت سرور طبق اطلاعات شما (⛔ تأییدنشده)

- Easy!Appointments روی `0.0.0.0:9090` **بدون HTTPS** — ریسک بالا برای داده واقعی مشتری.
- phpMyAdmin روی `127.0.0.1:9091` — **خوب** (فقط لوکال). توصیه: در تولید اصلاً لازم نیست؛ اگر می‌ماند، فقط از طریق تونل SSH استفاده شود و رمز root دیتابیس چرخانده شود.
- هم MySQL سیستمی (`127.0.0.1:3306`) و هم MySQL داخلی Docker فعال‌اند → **دو دیتابیس موازی**: خطر سردرگمی بکاپ/ریستور. باید یکی به‌عنوان مرجع تعیین شود.
- نرم‌افزار «نورستان» (Node.js، دامنه `hoosna1402.ir`) روی همان سرور با ۲GB RAM اجرا می‌شود → برای `mysqld + php-fpm + nginx + Node` بسیار کم است؛ **افزایش swap الزامی است** و باید مصرف حافظه اندازه‌گیری شود.
- Docker 29.1.3 با `docker-compose` نسخه 1.29.2 (با خط تیره) — طبق اطلاعات شما؛ جزئیات در بخش ۵-۲ (انحراف مخزن از سرور).

### ۴-۴) انحراف مخزن از سرور (مهم برای قابلیت بازتولید)

| موضوع | در گیت (این checkout) | طبق اطلاعات شما روی سرور |
| --- | --- | --- |
| `docker-compose.yml` | استک توسعه upstream با **۱۰ سرویس** (nginx, mysql, phpmyadmin, mailpit, swagger, baikal, openldap, phpldapadmin) و **رمزهای سخت‌کدشده `secret`/`password`** و pma روی `${PHPMYADMIN_PORT:-8080}` | نسخه دستی با ۳-۴ سرویس: `php-fpm`, `nginx`, `mysql`, `phpmyadmin` روی 9091 |
| `Dockerfile` | وجود ندارد (تصویر در `docker/php-fpm/Dockerfile` = `php:8.5-fpm`) | فایل `Dockerfile` سفارشی در ریشه: `php:8.2-fpm` + اکستنشن‌ها + Composer + Node |
| `docker/nginx/nginx.conf` | کانفیگ توسعه upstream | نسخه سفارشی «برای پشتیبانی `/assets/`» |

**نتیجه:** استقرار فعلی از گیت قابل بازتولید نیست (Configuration Drift). این یعنی اگر کانتینر بازسازی شود ممکن است
برنامه بالا نیاید یا با کانفیگ ناامن بالا بیاید. رفع این مورد خودش یک گام مستقل در نقشه راه است (بخش ۷).

---

## بخش ۵ — فهرست شکاف‌ها نسبت به سند (اولویت‌بندی‌شده)

راهنما: ✅ موجود | ⚠️ ناقص | ❌ ناموجود — اولویت: **P0** امنیت/تولید · **P1** هسته کسب‌وکار · **P2** تجربه کاربری/گزارش · **P3** اختیاری

| # | قابلیت | وضعیت | اولویت | توضیح شکاف |
| --- | --- | --- | --- | --- |
| ۱ | فایل زبان فارسی کامل | ✅ | — | ۵۸۹/۵۸۹ کلید |
| ۲ | RTL صفحه رزرو و پنل | ✅ | — | انجام‌شده در گام ۲ |
| ۳ | تقویم شمسی + ذخیره میلادی | ✅ | — | انجام‌شده در گام ۲ |
| ۴ | هفته از شنبه + تعطیلات ایران | ✅ | — | انجام‌شده در گام ۲ |
| ۵ | ارقام فارسی + قلم وزیرمتن | ✅ | — | انجام‌شده در گام ۲ |
| ۶ | ورود مشتری با موبایل + OTP (هش، انقضا ۲ دقیقه، ۵ تلاش، rate limit) | ❌ | **P1** | هیچ زیرساختی نیست؛ ستون `phone_number` در `ea_users` هست |
| ۷ | اعتبارسنجی شماره ایرانی `09xxxxxxxxx` | ❌ | **P1** | فقط اعتبارسنجی عمومی تلفن |
| ۸ | پروفایل مشتری + تاریخچه + لغو/جابه‌جایی + رزرو مجدد | ⚠️ | **P1** | `Booking_cancellation` و `reschedule` با هش نوبت هست؛ پنل پروفایل و رزرو مجدد یک‌کلیکی نیست |
| ۹ | ورود مدیر/آرایشگر + حذف رمز پیش‌فرض | ⚠️ | **P0** | `console seed` رمز `administrator` می‌سازد |
| ۱۰ | ماژول `SmsProvider` + TextBee (صف، retry/backoff، mock، لاگ، rate limit) | ❌ | **P1** | هیچ زیرساخت پیامکی نیست |
| ۱۱ | health check دستگاه TextBee + هشدار پنل | ❌ | **P1** | — |
| ۱۲ | کاربردهای پیامک (OTP، تأیید، یادآور ۲۴/۲، لیست انتظار، تبلیغاتی) | ❌ | **P1** | — |
| ۱۳ | چند خدمت در یک نوبت | ❌ | **P1** | یک ستون `id_services` |
| ۱۴ | buffer بین نوبت‌ها | ❌ | **P1** | مفهوم buffer نیست |
| ۱۵ | جلوگیری از رزرو هم‌زمان (تراکنش/قید) | ❌ | **P0** | بخش ۲-۸ ردیف ۱ |
| ۱۶ | hold ده‌دقیقه‌ای پرداخت | ❌ | **P1** | — |
| ۱۷ | state machine نوبت | ❌ | **P1** | ستون `status` آزاد، بدون قاعده گذار |
| ۱۸ | پرداخت بیعانه زرین‌پال پشت interface + idempotent | ❌ | **P1** | هیچ ماژول پرداختی نیست |
| ۱۹ | سیاست لغو و سوخت بیعانه | ❌ | **P1** | فقط `cancellation_timeout` ساده |
| ۲۰ | لیست انتظار + پیامک به نفر بعد | ❌ | **P2** | — |
| ۲۱ | امتیاز و نظر پس از سرویس با تأیید مدیر | ❌ | **P2** | — |
| ۲۲ | گالری نمونه‌کار + قیمت/مدت هر خدمت | ⚠️ | **P2** | قیمت/مدت هست؛ گالری و آپلود تصویر نیست |
| ۲۳ | کد تخفیف و باشگاه امتیاز | ❌ | **P2** | — |
| ۲۴ | یادداشت خصوصی مشتری، بلاک، شمارش no-show | ❌ | **P1** | — |
| ۲۵ | گزارش‌ها (درآمد، ساعت شلوغ، وفادار/غیرفعال، نرخ لغو) | ❌ | **P2** | هیچ کنترلر/کتابخانه گزارش نیست |
| ۲۶ | کمپین پیامکی مشتریان غیرفعال با تأیید | ❌ | **P3** | — |
| ۲۷ | محدودسازی آرایشگر به نوبت‌های خودش | ⚠️ | **P0** | نقش‌ها و `Permissions` هست ولی فیلتر سرور در API و برخی مسیرها باید سخت شود (IDOR) |
| ۲۸ | PWA و mobile-first | ❌ | **P3** | manifest/SW نیست |
| ۲۹ | ICS و Google Calendar فعال و فارسی | ⚠️ | **P2** | کتابخانه هست؛ endpoint عمومی ICS و ترجمه فارسی نیست |
| ۳۰ | ربات تلگرام (اختیاری) | ❌ | **P3** | — |
| ۳۱ | seed idempotent (مدیر، آرایشگر، ۶ خدمت، تنظیمات، ساعت کاری) | ⚠️ | **P1** | `console seed` غیر idempotent است و داده ایرانی/واقعی ندارد |
| ۳۲ | اسکریپت `scripts/create-admin` | ❌ | **P1** | — |
| ۳۳ | اسکریپت‌های دیپلوی/بکاپ (docker, ufw, fail2ban, swap, TLS) | ❌ | **P0** | چیزی در مخزن نیست |
| ۳۴ | `.env.example` + توقف برنامه در نبود کلید | ❌ | **P0** | بخش ۴-۱ |
| ۳۵ | Rate limiting (رزرو عمومی، OTP، API) | ⚠️ | **P0** | بخش ۴-۱ |
| ۳۶ | CSRF | ✅ | — | فعال؛ تقویت مسیر رزرو در گام ۴ |
| ۳۷ | هدرهای امنیتی / CSP / HSTS | ⚠️ | **P0** | HSTS نیاز به TLS در reverse proxy دارد |
| ۳۸ | جلوگیری از IDOR | ⚠️ | **P0** | بازبینی سیستماتیک لازم است |
| ۳۹ | Audit log تغییرات مدیریت | ❌ | **P2** | — |
| ۴۰ | لاگ ساختارمند | ⚠️ | **P2** | Monolog هست، استفاده ساختارمند نیست |
| ۴۱ | endpoint سلامت (`/health`) | ❌ | **P0** | — |
| ۴۲ | تست خودکار رزرو/OTP/پرداخت | ❌ | **P1** | فعلاً ۳۶ تست بومی‌سازی |
| ۴۳ | README دیپلوی فارسی گام‌به‌گام | ⚠️ | **P1** | — |
| ۴۴ | **استقرار قابل بازتولید (compose/Dockerfile/nginx در گیت)** | ❌ | **P0** | بخش ۴-۴ — جدید، از ممیزی این مرحله |
| ۴۵ | **محافظت وب از `storage/` و فایل‌های حساس** | ❌ | **P0** | بخش ۴-۱ — جدید |
| ۴۶ | **بکاپ خودکار + تست بازیابی + rollback مهاجرت** | ❌ | **P0** | جدید |
| ۴۷ | **دامنه + TLS** | ❌ | **P0** | در انتظار تصمیم شما (دامنه مشخص نشده) |

---

## بخش ۶ — نقشه راه پیشنهادی مراحل

> **توجه:** متن کامل «مراحل ۱ تا ۱۰» در پیام این نشست برای من ارسال نشد (به‌جای آن عبارت «متن کامل مثل قبل» آمده است).
> نقشه زیر را از جدول شکاف‌های گام ۱ (سند `docs/fa/step-01-analysis.md`) و خواسته‌های صریح همین پیام بازسازی کرده‌ام.
> شماره‌گذاری با «مرحله ۰ = همین ممیزی» هم‌تراز شده است. **اگر متن دقیق مراحل شما متفاوت است، بفرستید تا جایگزین کنم.**

| گام | موضوع | پوشش شکاف‌ها | معیار پذیرش پیشنهادی |
| --- | --- | --- | --- |
| ۰ | ممیزی و خط پایه | — | همین گزارش |
| ۱ | **زیرساخت تولید و امنیت**: loader `.env` + `config.php` از env، compose/Dockerfile/nginx نسخه‌دار و امن (بلاک `storage`/`application`/`config.php`)، دامنه + TLS، firewall/swap/fail2ban، health endpoint، بکاپ خودکار + تست restore، اسکریپت‌های `scripts/` | ۳۳،۳۴،۳۵،۳۷،۴۱،۴۴،۴۵،۴۶،۴۷،۹ | همه چک‌های ۴-۲ سبز + `docker compose up` از صفر روی یک محیط تازه بالا بیاید |
| ۲ | **هویت مشتری**: OTP موبایل (هش، انقضا، سقف تلاش، rate limit)، اعتبارسنجی شماره ایرانی، پنل مشتری (تاریخچه/لغو/جابجایی/رزرو مجدد)، حذف رمز پیش‌فرض مدیر، `scripts/create-admin`، seed idempotent | ۶،۷،۸،۹،۳۱،۳۲ | ورود با OTP + تست‌های واحد/یکپارچه OTP |
| ۳ | **پیامک**: `SmsProvider` interface + `TextBeeProvider`، صف با retry/backoff، حالت mock، لاگ، rate limit، health check، یادآور ۲۴ و ۲ ساعته (کرون) | ۱۰،۱۱،۱۲ | ارسال واقعی تستی + شبیه‌سازی قطعی سرویس بدون از دست رفتن پیام |
| ۴ | **موتور نوبت**: state machine، تراکنش + قفل + قید یکتا برای جلوگیری از رزرو هم‌زمان، چند خدمت در یک نوبت، buffer، hold ده‌دقیقه‌ای | ۱۳،۱۴،۱۵،۱۶،۱۷ | تست هم‌زمانی (دو درخواست موازی → یک نوبت) + تست گذار وضعیت |
| ۵ | **پرداخت**: زرین‌پال پشت interface، idempotency، سیاست لغو و سوخت بیعانه | ۱۸،۱۹ | تست sandbox پرداخت + عدم کسر دوباره |
| ۶ | **تجربه مشتری**: لیست انتظار، نظرات با تأیید، گالری نمونه‌کار، کد تخفیف/باشگاه امتیاز، یادداشت خصوصی/بلاک/no-show | ۲۰،۲۱،۲۲،۲۳،۲۴ | گردش کامل مشتری روی محیط staging |
| ۷ | **گزارش و کنترل**: گزارش درآمد/ساعت شلوغ/وفادار-غیرفعال/نرخ لغو، کمپین پیامکی با تأیید، audit log | ۲۵،۲۶،۳۹ | صحت اعداد گزارش با داده واقعی |
| ۸ | **سخت‌سازی و تست**: بازبینی IDOR همه endpointها، CSP، تست‌های یکپارچه رزرو/OTP/پرداخت، لاگ ساختارمند | ۲۷،۳۵،۳۸،۴۰،۴۲ | عبور کل تست‌ها در CI |
| ۹ | **مستندسازی و تحویل**: README فارسی دیپلوی، runbook عملیاتی، PWA | ۲۸،۴۳ | یک نفر دیگر بتواند از صفر روی سرور تازه نصب کند |
| ۱۰ | **اختیاری‌ها**: ICS عمومی، ربات تلگرام | ۲۹،۳۰ | — |

---

## بخش ۷ — معیار پذیرش مرحله ۰

| معیار | وضعیت |
| --- | --- |
| گزارش نسخه، فریم‌ورک، ساختار، نقاط توسعه، ایمیل/اعلان، REST API و openapi، ترجمه/فارسی/RTL، منطق اسلات و رزرو | ✅ بخش‌های ۱ تا ۳ |
| ممیزی امنیتی سرویس‌ها/پورت‌ها/رمز پیش‌فرض/PHP/دسترسی `storage` و `config` | ⚠️ بخش ۴-۱ کامل (از کد و کانفیگ) · **۴-۲ و ۴-۳ نیازمند دسترسی به سرور** |
| فهرست شکاف‌های اولویت‌بندی‌شده | ✅ بخش ۵ (۴۷ ردیف) |
| بدون تغییر کد | ✅ `git status` پس از این گام فقط دو فایل مستندات نشان می‌دهد |
| **درصد تکمیل فعلی** | **~۷۰٪** — تنها بخش باقی‌مانده، ممیزی زنده سرور است که به دسترسی وابسته است |

---

## بخش ۸ — تصمیم‌های لازم از شما

1. **دامنه نهایی** برای Easy!Appointments (سند می‌گوید مشخص نشده). پیشنهاد: یک زیر‌دامنه از `hoosna1402.ir` (مثلاً `nobat.hoosna1402.ir`) تا گواهی TLS جدا و ایزوله بگیرد و به برنامه نورستان آسیبی نزند. ⛔ **هیچ تغییر DNS/پورتی بدون تأیید شما انجام نمی‌شود.**
2. **راه دسترسی من به سرور** (یکی را انتخاب کنید — بخش ۱۰).
3. **تأیید متن کامل مراحل ۱ تا ۱۰** اگر با نقشه بخش ۶ تفاوت دارد.
4. **تأیید اجرای چک‌لیست فقط-خواندنی ۴-۲** (شامل `docker ps`, `docker exec ... cat`, `ss -tlnp`, `systemctl` نیست جز `ufw status`). هیچ‌کدام تغییری در سرویس‌ها ایجاد نمی‌کنند، اما طبق قاعده ۱۲ منتظر تأیید صریح شما می‌مانم.
5. **انتخاب یک MySQL مرجع** (سیستمی یا Docker) و اجازه بستن/غیرفعال‌سازی دیگری (با هشدار قبلی).

---

## بخش ۹ — ریسک‌های باقی‌مانده پس از مرحله ۰

| ریسک | شدت | تا کِی |
| --- | --- | --- |
| افشای `storage/backups` و `storage/sessions` از وب (اگر nginx سرور بلاک نکرده باشد) | بحرانی | تا گام ۱ |
| نبود HTTPS روی داده واقعی مشتری | بالا | تا گام ۱ |
| رزرو تکراری در رخداد هم‌زمانی | بالا | تا گام ۴ |
| رمز پیش‌فرض مدیر `administrator` (اگر seed اجرا شده باشد) | بالا | تا گام ۲ |
| انحراف کانفیگ سرور از گیت | متوسط | تا گام ۱ |
| نبود بکاپ خودکار و تست‌شده | متوسط | تا گام ۱ |
| دو پایگاه‌داده موازی روی سرور | متوسط | تا گام ۱ |
| منابع کم سرور (۲GB / ۱ core با دو برنامه) | متوسط | پایش در گام ۱ |
| نبود IP و منبع اصلی در `CUSTOMIZATIONS.md` — که در همین گام ساخته شد | — | برطرف شد |

---

## پیوست — کلیدهای تنظیمات مرتبط با این گزارش

| تنظیم | محل | مقدار فعلی |
| --- | --- | --- |
| `base_url` | `application/config/config.php` | پویا از `HTTP_HOST` |
| `environment` | `index.php` | از `APP_ENV` یا `Config::DEBUG_MODE` |
| `log_threshold` / `log_path` | `application/config/config.php` | `1` / `storage/logs/` |
| `sess_driver` / `sess_save_path` / `sess_expiration` | همان | `files` / `storage/sessions` / ۶۰۴۸۰۰ ثانیه |
| `csrf_protection` / `csrf_exclude_uris` | همان | `true` / `api/v1.*`, `booking.*`, ... |
| `rate_limiting` | همان | `true` (اعمال فقط در چند مسیر) |
| `book_advance_timeout` | جدول `ea_settings` | پیش‌فرض ۰ دقیقه |
| `future_booking_limit` | جدول `ea_settings` | پیش‌فرض ۹۰ روز |
| `appointment_status_options` | جدول `ea_settings` | `["Booked","Confirmed","Rescheduled","Cancelled","Draft"]` |
| `calendar_type` / `persian_digits` / `display_timezone` / `first_weekday` | جدول `ea_settings` (migration 071) | `jalali` / `1` / `Asia/Tehran` / `saturday` |
