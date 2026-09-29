# گزارش مرحله ۱ — تحلیل کد، فورک، اجرای محلی و گزارش شکاف‌ها

> سند تحویل مرحله ۱ از پروژه «سامانه نوبت‌دهی آرایشگاه مردانه»
> مبنا: فورک مخزن `abolfazlghasemi2001/easyappointments` از Easy!Appointments (GPL-3.0)

---

## ۱) خلاصه اجرایی

- پروژه بر پایه **Easy!Appointments نسخه 1.5.2** (فایل `composer.json`) و **CodeIgniter 3 (نسخه سفارشی‌شده با پیشوند `EA_`)** است. نسخه داخلی برنامه در `application/config/app.php` برابر `1.6.0` اعلام شده است.
- فورک روی همین مخزن انجام شد و شاخه کاری `arena/01a0eef1-easyappointments` روی ریموت `origin` (فورک کاربر) ثبت شده است.
- اجرای محلی با Docker در محیط سندباکس **ممکن نبود** (نه باینری Docker وجود دارد و نه رجیستری‌های آن قابل دسترسی هستند؛ مستندات در بخش ۸). در عوض برنامه **به‌صورت واقعی و کامل روی همین سندباکس اجرا و نصب شد** با ترکیب زیر:
  - PHP 8.4 (زمان اجرای WebAssembly از `@php-wasm/node`)
  - بازسازی `vendor/` از `composer.lock` (بدون Packagist) با اسکریپت `minicomposer.py`
  - پایگاه‌داده SQLite فقط برای محیط توسعه/تست (تولید همچنان MySQL/MariaDB است)
- خروجی قابل مشاهده: صفحه رزرو (`/`) و صفحه ورود مدیریت (`/index.php/login`) با کد HTTP 200 و REST API فعال (پاسخ 401 برای درخواست بدون کلید، مطابق انتظار).
- مهم‌ترین شکاف‌ها نسبت به فهرست درخواستی: **OTP/ورود با موبایل، پیامک، پرداخت، تقویم شمسی/RTL، «هرکدام»، hold، state machine نوبت، لیست انتظار، نظرات، گالری، تخفیف/باشگاه مشتریان، یادداشت خصوصی/بلاک/no-show، گزارش‌ها، کمپین پیامکی، PWA، audit log و .env** وجود ندارند یا ناقص‌اند (جدول کامل در بخش ۹).

---

## ۲) معماری پروژه

| بخش | توضیح |
| --- | --- |
| فریم‌ورک | CodeIgniter 3 با هسته سفارشی‌شده (`application/core/EA_*.php`)، الگوی MVC |
| زبان/نسخه | PHP `>=8.2` (کامپوزر)، در محیط تولید پیشنهادی: PHP 8.3/8.4 |
| مسیرهای اصلی | `index.php` (بوت‌استرپ)، `application/` (کد برنامه)، `system/` (هسته CI3)، `assets/` (JS/CSS)، `docker/`، `tests/`، `docs/` |
| کنترلرها | ۳۸ کنترلر در `application/controllers` + ۱۳ کنترلر REST در `application/controllers/api/v1` |
| مدل‌ها | ۱۵ مدل در `application/models` (Appointments, Users, Customers, Providers, Services, Settings, Webhooks, Working_plan_exceptions, ...) |
| کتابخانه‌ها | `Accounts` (احراز هویت)، `Availability` (موتور ظرفیت)، `Notifications` (ایمیل)، `Email_messages`، `Ics_file`، `Google_sync`، `Caldav_sync`، `Webhooks_client`، `Permissions`، `Timezones`، `Jitsi_client`، `Altcha_client`، `Ldap_client`، `Cleanup`، `Instance` |
| هوک‌ها | `application/config/hooks.php`: `security_headers` (post_controller_constructor) و `storage_cleanup` (post_system) |
| مهاجرت‌ها | ۷۰ فایل در `application/migrations` (آخرین: `069_add_altcha_settings`)؛ نصب از طریق `Installation` یا کنسول |
| نصب/کنسول | `php index.php console install|migrate|migrate fresh|seed|backup` (مستند در `docs/console.md`) |
| پایگاه‌داده | MySQL/MariaDB (`mysqli`، پیشوند جدول `ea_`)، ۱۵ جدول؛ SQLite فقط برای محیط توسعه این سندباکس |
| تقویم‌ها | Google Calendar (`google_sync`) و CalDAV (`caldav_sync`) + فایل ICS (`Ics_file`) |
| وب‌هوک | جدول `webhooks` + ستون `secret_header` (migration 060) و کتابخانه `Webhooks_client` |
| تست | `phpunit.xml` (بوت‌استرپ `index.php`)، فقط ۲ کلاس تست فعلی: `ArrayHelperTest` و `ValidationHelperTest` |

**نکته:** در این نسخه عملاً هیچ فایل تولیدی/secret داخل مخزن نیست؛ `config.php` در `.gitignore` است و `vendor/` هم نادیده گرفته می‌شود.

---

## ۳) سیستم ترجمه و فارسی‌سازی موجود

- ترجمه‌ها در `application/language/<lang>/translations_lang.php` (کلید → مقدار) هستند و ۴۲ زبان وجود دارد.
- **فارسی کامل است**: ۵۷۲ کلید انگلیسی و ۵۷۲ کلید فارسی، صفر کلید گمشده/خالی.
- زبان فا در `application/config/config.php` با کد `'fa' => 'persian'` و در فهرست `available_languages` ثبت شده است.
- **شکاف‌ها:** هیچ استایل RTL در پروژه نیست (تنها یک مورد `.ui-timepicker-rtl` در `assets/css/general.scss`)، تقویم میلادی است، اعداد فارسی نمایش داده نمی‌شوند، فونت وزیرمتن وجود ندارد، و هفته از شنبه شروع نمی‌شود.

---

## ۴) سیستم اعلان‌ها

- فقط **ایمیل**: کتابخانه `Notifications` + قالب‌های `application/views/emails` + `Email_messages`، با پیکربندی SMTP در `application/config/email.php` و تنظیمات SMTP در دیتابیس (`settings`).
- ارسال‌ها داخل همان درخواست وب انجام می‌شوند (بدون صف/retry/backoff).
- هیچ سرویس پیامکی (SMS) در پروژه وجود ندارد؛ وب‌هوک تنها مکانیزم اطلاع‌رسانی خارجی است.

---

## ۵) REST API و یکپارچه‌سازی‌ها

- مسیرها در `application/config/routes.php` با تابع `route_api_resource()` برای منابع زیر تعریف شده‌اند:
  `appointments`, `admins`, `service_categories`, `customers`, `providers`, `secretaries`, `services`, `unavailabilities`, `webhooks`, `blocked_periods` + مسیرهای ویژه `settings` و `availabilities`.
- احراز هویت API با کلید (`x-api-key`) و مدیریت آن در کنترلر `Api_settings`؛ درخواست بدون کلید پاسخ **401** می‌گیرد (تست شد).
- مشخصات کامل API در `openapi.yml` (۷۹ کیلوبایت، سازگار با Swagger UI موجود در `docker-compose.yml`).
- محافظت‌ها: CSRF فعال (`config.php`)، هدرهای امنیتی سراسری (`routes.php` + هوک)، هلپر `rate_limit_helper.php` (مصرف در `Login`، `Booking_cancellation`، `Privacy` و `EA_Controller`) — اما rate limit برای رزرو عمومی و OTP وجود ندارد.

---

## ۶) موتور ظرفیت و منطق نوبت (وضعیت فعلی)

- `application/libraries/Availability.php` (۶۴۷ خط) شامل: پلن کاری هفتگی هر آرایشگر، استراحت‌ها (breaks)، استثناهای پلن کاری (`working_plan_exceptions`)، روزهای مسدود (`blocked_periods`)، عدم دسترسی‌ها (`unavailabilities`)، حالت «چند نفر در یک نوبت» (`attendants_number`)، فاصله اسلات هر خدمت (`slot_interval`، پیش‌فرض ۱۵)، حداقل پیش‌رزرو (`book_advance_timeout`) و سقف پیش‌رزرو (پیش‌فرض ۹۰ روز).
- **چند آرایشگر در یک نوبت**: پشتیبانی می‌شود (کتابخانه برای هر provider جدا محاسبه می‌کند و `Booking::search_any_provider()` آرایشگر آزاد را پیدا می‌کند).
- **چند خدمت در یک نوبت**: در هسته پشتیبانی نمی‌شود؛ هر نوبت به یک `id_services` گره خورده است (نیازمند توسعه در مراحل بعد).
- **buffer بین نوبت‌ها**: وجود ندارد.
- **جلوگیری از تداخل هم‌زمان (race)**: تابع `Appointments_model::has_provider_conflict()` وجود دارد (مصرف در `Calendar` برای نوبت‌های دستی) اما **مسیر رزرو عمومی (`Booking::register`) آن را صدا نمی‌زند** و هیچ constraint/قفل دیتابیسی هم وجود ندارد → دو درخواست هم‌زمان می‌توانند نوبت تکراری بسازند. باید در مرحله ۴ رفع شود (تراکنش + قفل + قید یکتایی).

---

## ۷) وضعیت فورک

- ریموت `origin` به `https://github.com/abolfazlghasemi2001/easyappointments.git` (فورک کاربر) اشاره می‌کند.
- شاخه کاری: `arena/01a0eef1-easyappointments` با کامیت مبنا `450ba15` (۲۰۲۶-۰۹-۰۹).
- فایل `LICENSE` (GPL-3.0) موجود است؛ فایل `NOTICE` (درخواست‌شده در فهرست) وجود ندارد و در مرحله ۸ ساخته می‌شود.

---

## ۸) اجرای محلی

### ۸-۱) چرا Docker در این سندباکس ممکن نیست (شواهد)

| مورد | نتیجه بررسی |
| --- | --- |
| باینری Docker / dockerd / docker-compose | نصب نیست (`docker: command not found`؛ سوکت `/var/run/docker.sock` وجود ندارد) |
| نصب با apt | مخازن Debian/Ubuntu در دسترس نیستند (`Connection failed ... deb.debian.org`) |
| رجیستری Docker Hub (`registry-1.docker.io`) | در دسترس نیست |
| Packagist / getcomposer / composer | در دسترس نیستند (`packagist.org`، `getcomposer.org` و `dl.static-php.dev` بلاک‌اند) |
| hosts در دسترس | `github.com`، `api.github.com`، `codeload.github.com`، `registry.npmjs.org`، `pypi.org` |

بنابراین «اجرای محلی با Docker» در سندباکس ممکن نشد، اما همان برنامه **بدون Docker و به‌صورت واقعی اجرا شد** (زیر). روی VPS خریداری‌شده، Docker و Compose در مرحله ۸ به‌صورت کامل پیاده و مستند می‌شوند.

### ۸-۲) راهکار اجرای محلی به‌کاررفته (سندباکس)

1. **بازسازی وابستگی‌ها** (بدون Packagist):
   `python3 /home/user/.devtools/minicomposer.py /home/user/easyappointments`
   خروجی: ۵۲ بسته از `composer.lock` (از طریق zipball گیت‌هاب) + ساخت `vendor/autoload.php` (PSR-4/PSR-0/classmap/files).
2. **زمان اجرای PHP**: بسته‌های `@php-wasm/node` و `@php-wasm/node-8-4` (PHP **8.4.25**) در `/home/user/.arena-tools/phptest` نصب و توسط رابط‌های زیر استفاده می‌شوند:
   - `/home/user/.devtools/php/run.mjs` → اجرای اسکریپت‌های PHP و PHPUnit:
     `node /home/user/.devtools/php/run.mjs index.php console install`
     `node /home/user/.devtools/php/run.mjs --phpunit --configuration phpunit.xml`
   - `/home/user/.devtools/php/server.mjs` → وب‌سرور محلی روی پورت 8080 (پیش‌نمایش زنده).
3. **پایگاه‌داده توسعه**: SQLite (فایل `storage/easyappointments.sqlite`)
   - `application/config/development/database.php` و `application/config/testing/database.php` (فقط `APP_ENV=development|testing`؛ محیط `production` دست‌نخورده و همچنان `mysqli` است).
   - نصب کامل با ۱۵ جدول و داده پیش‌فرض: مدیر `administrator` / `administrator` (John Doe)، آرایشگر Jane Doe، مشتری James Doe و یک خدمت نمونه.
   - نکته شفافیت: چون مهاجرت‌های رسمی برای MySQL نوشته شده‌اند (`ENGINE InnoDB` و `ALTER ... ADD CONSTRAINT`)، تولید schema روی SQLite با یک **کپی یک‌بارمصرف پچ‌شده** در `/tmp/ea-boot` انجام شد؛ **هیچ فایلی از `system/` یا migrations در مخزن واقعی تغییر نکرد** (`git status` پاک است). این کپی فقط ابزار توسعه سندباکس است.
4. **نتیجه اجرا**:
   - `GET /` → کد 200 (صفحه رزرو)
   - `GET /index.php/login` → کد 200 (ورود مدیریت)
   - `GET /index.php/calendar` → کد 302 (ریدایرکت به ورود؛ صحیح)
   - `GET /index.php/api/v1/services` بدون کلید → کد 401 (صحیح)

### ۸-۳) دستورات روی VPS (محیط واقعی، مرحله ۸ تکمیل می‌شود)

```
git clone https://github.com/abolfazlghasemi2001/easyappointments.git
cd easyappointments
cp .env.example .env         # در مرحله ۸ ساخته می‌شود
docker compose up -d --build # استک تولید: caddy + php-fpm + mariadb + redis
```

---

## ۹) گزارش شکاف‌ها نسبت به فهرست درخواستی

راهنمای وضعیت: ✅ موجود | ⚠️ ناقص | ❌ ناموجود

| # | قابلیت درخواستی | وضعیت | توضیح شکاف |
| --- | --- | --- | --- |
| 1 | فایل زبان فارسی کامل | ✅ | ۵۷۲/۵۷۲ کلید؛ فقط باید کلیدهای جدید ماژول‌های آینده اضافه شود |
| 2 | RTL برای صفحه رزرو و پنل مدیریت | ❌ | هیچ استایل RTL در پروژه نیست |
| 3 | تقویم شمسی (نمایش) + ذخیره میلادی/UTC | ❌ | همه‌جا میلادی است؛ تبدیل تاریخ و نمایش `Asia/Tehran` باید اضافه شود |
| 4 | هفته از شنبه + تعطیلات رسمی ایران | ❌ | ترتیب روزها میلادی است؛ تعطیلات رسمی مدل ندارد |
| 5 | اعداد فارسی + فونت وزیرمتن | ❌ | نه فرمت اعداد و نه فونت |
| 6 | ورود مشتری با موبایل + OTP ۶ رقمی (هش، انقضا ۲ دقیقه، ۵ تلاش، rate limit) | ❌ | احراز هویت فقط رمز عبور است؛ ستون `phone_number` در `users` وجود دارد |
| 7 | اعتبارسنجی شماره ایرانی `09xxxxxxxxx` | ❌ | فقط اعتبارسنجی عمومی تلفن |
| 8 | پروفایل مشتری + تاریخچه + لغو/جابه‌جایی + رزرو مجدد | ⚠️ | `Booking_cancellation` و `reschedule` با هش نوبت وجود دارد، اما پنل پروفایل مشتری و «رزرو مجدد یک‌کلیکی» نیست |
| 9 | ورود مدیر/آرایشگر با OTP و حذف رمز پیش‌فرض | ❌ | ورود رمز عبور + نصب داده پیش‌فرض `administrator/administrator` |
| 10 | ماژول SmsProvider + TextBee (صف، retry/backoff، mock، لاگ، rate limit) | ❌ | هیچ زیرساخت پیامکی وجود ندارد |
| 11 | health check دستگاه TextBee + هشدار پنل | ❌ | ناموجود |
| 12 | کاربردهای پیامک (OTP، تأیید، یادآوری ۲۴/۲ ساعت، لیست انتظار، تبلیغاتی) | ❌ | ناموجود |
| 13 | چند خدمت در یک نوبت | ❌ | مدل داده یک `id_services` برای هر نوبت دارد |
| 14 | buffer بین نوبت‌ها | ❌ | مفهوم buffer وجود ندارد |
| 15 | جلوگیری از رزرو هم‌زمان (قفل/constraint) | ❌ | `has_provider_conflict` در مسیر عمومی رزرو استفاده نمی‌شود؛ بدون قید دیتابیس |
| 16 | hold ده‌دقیقه‌ای پرداخت | ❌ | ناموجود |
| 17 | state machine نوبت | ❌ | ستون وضعیت متن‌آزاد `status` بدون قواعد گذار |
| 18 | پرداخت بیعانه زرین‌پال پشت interface + idempotent | ❌ | هیچ ماژول پرداختی وجود ندارد |
| 19 | سیاست لغو و سوخت بیعانه | ❌ | فقط تنظیم `cancellation_timeout` ساده |
| 20 | لیست انتظار + پیامک به نفر بعد | ❌ | ناموجود |
| 21 | امتیاز و نظر پس از سرویس با تأیید مدیر | ❌ | ناموجود |
| 22 | گالری نمونه‌کار آرایشگر + قیمت/مدت هر خدمت | ⚠️ | قیمت و مدت خدمت وجود دارد؛ گالری و آپلود تصویر مخصوص نمونه‌کار نیست |
| 23 | کد تخفیف و باشگاه امتیاز | ❌ | ناموجود |
| 24 | یادداشت خصوصی مشتری، بلاک مشتری، شمارش no-show | ❌ | ناموجود |
| 25 | گزارش‌ها (درآمد، ساعت شلوغ، وفادار/غیرفعال، نرخ لغو) | ❌ | هیچ کنترلر/کتابخانه گزارش وجود ندارد |
| 26 | کمپین پیامکی مشتریان غیرفعال با تأیید | ❌ | ناموجود |
| 27 | محدودسازی آرایشگر به نوبت‌های خودش | ⚠️ | نقش‌ها و `Permissions` وجود دارد و آرایشگر فقط provider خودش را می‌بیند، اما فیلتر سمت سرور در همه مسیرها (از جمله API) باید بازبینی و سخت‌تر شود |
| 28 | PWA و mobile-first | ❌ | manifest/service worker وجود ندارد؛ فرانت‌اند jQuery/Bootstrap قدیمی است |
| 29 | ICS و Google Calendar (فعال و فارسی) | ⚠️ | کتابخانه‌ها و تنظیمات موجودند (`Ics_file`, `google_sync`) اما endpoint عمومی خروجی ICS و ترجمه فارسی آن‌ها نیست |
| 30 | ربات تلگرام (اختیاری) | ❌ | ناموجود |
| 31 | seed idempotent (مدیر، آرایشگر، ۶ خدمت، تنظیمات) | ⚠️ | `console seed` وجود دارد اما idempotent نیست (دوباره‌کاری خطای «username تکراری» می‌دهد) و ساعت کاری/خدمات نمونه درخواستی را ندارد |
| 32 | اسکریپت `scripts/create-admin` | ❌ | ناموجود |
| 33 | اسکریپت‌های سرور/دیپلوی/بکاپ (Docker, ufw, fail2ban, swap, Caddy) | ❌ | هیچ اسکریپتی نیست؛ `docker-compose.yml` فعلی صرفاً توسعه است و پسوردهای سخت‌کدشده (`secret`/`password`) دارد که برای تولید ممنوع است |
| 34 | فایل `.env.example` + توقف برنامه در صورت نبود کلید | ❌ | مکانیزم env وجود ندارد؛ هلپر `env()` فقط از `$_ENV` می‌خواند و چیزی آن را پر نمی‌کند |
| 35 | Rate limiting (عمومی/OTP/IP) | ⚠️ | هلپر `rate_limit_helper` و مصرف در Login/Privacy/Cancel موجود است؛ برای OTP و رزرو عمومی باید اضافه شود |
| 36 | CSRF | ✅ | فعال است (`$config['csrf_protection'] = true`) |
| 37 | هدرهای امنیتی | ⚠️ | هدرهای پایه در `routes.php` + هوک؛ CSP/HSTS سخت‌گیرانه نیست (HSTS باید در Caddy ست شود) |
| 38 | جلوگیری از IDOR | ⚠️ | کنترل دسترسی بر پایه نقش/هش نوبت است اما بازبینی سیستماتیک همه endpointها لازم است |
| 39 | Audit log تغییرات مدیریت | ❌ | ناموجود |
| 40 | لاگ ساختارمند | ⚠️ | Monolog در وابستگی‌ها هست؛ استفاده ساختارمند/JSON وجود ندارد |
| 41 | endpoint سلامت (health) | ❌ | ناموجود |
| 42 | تست خودکار رزرو/OTP/پرداخت | ❌ | فقط ۲ تست هلپری وجود دارد (۷ تست، ۸ assertion) |
| 43 | README دیپلوی گام‌به‌گام | ⚠️ | README انگلیسی موجود است؛ مستند فارسی دیپلوی در مرحله ۸ نوشته می‌شود |

---

## ۱۰) تغییرات موردنیاز در هسته و توجیه

قاعده کلی: تا جای ممکن همه‌چیز در ماژول/کنترلر/ویو/هوک جدید (`application/libraries`, `application/models`, `application/helpers`, `application/controllers`, `application/views`, `application/config/*`) اضافه می‌شود تا merge با upstream ممکن بماند. تغییرات ناگزیر در هسته، محدود و مستند خواهند بود:

| فایل هسته | تغییر پیشنهادی | توجیه |
| --- | --- | --- |
| `config.php` (ریشه) | خواندن مقادیر از `.env` + توقف در صورت نبود کلید | الزام صریح فهرست؛ این فایل در `.gitignore` است و در upstream هم بخشی از نصب است |
| `application/config/routes.php` | افزودن مسیرهای جدید (OTP، پرداخت، پنل مشتری، APIهای ماژول‌ها) | فایل مسیرها محل قانونی افزودن route است |
| `application/config/hooks.php` + `application/hooks/*` | هوک‌های جدید (audit log، امنیت، صف پیامک) | مکانیزم رسمی افزودن رفتار سراسری بدون دست‌زدن به کنترلرها |
| `application/core/EA_Controller.php` | احتمال افزودن میدل‌ور ساده برای rate limit نقش‌ها/audit | نقطه توسعه رسمی پروژه (کلاس پایه کنترلرها) |
| `application/controllers/Booking.php` | استفاده از سرویس جدید رزرو (قفل/state machine/هولد) به‌جای منطق درون‌خطی | جلوگیری از رزرو هم‌زمان بدون بازنویسی کل جریان رزرو |
| `application/libraries/Availability.php` | افزودن buffer و پشتیبانی چند خدمت با حداقل دست‌کاری | موتور ظرفیت قلب سیستم است؛ تغییر باید تست‌پذیر و برگشت‌پذیر باشد |
| `application/migrations/0xx_*.php` | فقط افزودن مهاجرت‌های جدید (بدون تغییر فایل‌های قبلی) | حفظ سازگاری به‌روزرسانی از upstream |

هیچ تغییری در `system/` (هسته CodeIgniter) اعمال نخواهد شد. تغییرات فایل‌های هسته در هر مرحله به‌صورت فهرست‌شده در گزارش همان مرحله تحویل می‌شود.

---

## ۱۱) وضعیت تست‌ها

- تست‌های موجود که همین حالا در سندباکس اجرا می‌شوند:
  ```
  node /home/user/.devtools/php/run.mjs --phpunit --configuration phpunit.xml
  → OK (7 tests, 8 assertions)
  ```
- تست‌های برنامه‌ریزی‌شده برای مراحل بعد (در پوشه `tests/`):
  1. `tests/Unit/Otp/OtpServiceTest.php` — تولید/هش/انقضا/سقف تلاش
  2. `tests/Unit/Booking/ConflictTest.php` — رزرو هم‌زمان، buffer، چند خدمت، «هرکدام»
  3. `tests/Unit/Booking/StateMachineTest.php` — گذارهای مجاز/غیرمجاز وضعیت نوبت
  4. `tests/Unit/Payment/ZarinpalGatewayTest.php` — امضای درخواست، تأیید idempotent، سوخت بیعانه
  5. `tests/Unit/Sms/TextBeeProviderTest.php` — ساخت بدنه/هدر، retry و backoff، حالت mock
  6. `tests/Unit/Localization/JalaliDateTest.php` — تبدیل شمسی/میلادی و مرزهای نیمه‌شب تهران
  7. `tests/Unit/Reports/CustomerSegmentationTest.php` — مشتری وفادار/غیرفعال و نرخ لغو

---

## ۱۲) گام بعدی (در انتظار تأیید شما)

مرحله ۲: فارسی‌سازی، RTL و تقویم شمسی — شامل:
1. استایل RTL برای صفحه رزرو و پنل مدیریت (بدون دست‌زدن به SCSS هسته؛ فایل‌های `rtl.scss` جدید + بارگذاری شرطی برای زبان فارسی)
2. ماژول تبدیل تاریخ شمسی (کتابخانه PHP + معادل JS) و نمایش تاریخ‌ها با `Asia/Tehran` در حالی که ذخیره‌سازی میلادی/UTC می‌ماند
3. شروع هفته از شنبه، پشتیبانی از تعطیلات رسمی ایران (جدول + تنظیمات مدیریتی)
4. اعداد فارسی در نمایش + فونت وزیرمتن (self-host، بدون CDN)
5. کلیدهای زبان جدید و تست‌های واحد تاریخ شمسی

## ۱۳) یادداشت‌های محیطی برای مراحل بعد

- اجرای PHP در این سندباکس از طریق `run.mjs` است؛ اگر sandbox بازنشانی شد، `npm install` در `/home/user/.arena-tools/phptest` و سپس `python3 /home/user/.devtools/minicomposer.py /home/user/easyappointments` کافی است.
- `config.php` محلی (gitignored) و پایگاه‌داده SQLite فقط ابزار توسعه‌اند و در تولید استفاده نمی‌شوند.
- فایل‌های `application/config/development/database.php` و `application/config/testing/database.php` (SQLite) عمداً در مخزن نگه داشته شده‌اند تا اجرای محلی/تست بدون MySQL ممکن باشد؛ اگر مایل نباشید، حذف آن‌ها اثری روی تولید ندارد.
