# مرحله ۳ — ورود با کد یک‌بارمصرف (OTP) و پنل مشتری

> همهٔ کارهای این مرحله **فقط کد و فایل** است؛ هیچ دستوری روی سرور اجرا نشده (SSH قطع است). تست‌ها در
> سندباکس با sqlite و PHPUnit اجرا شده‌اند و خروجی واقعی آن‌ها در همین سند آمده است.

## ۱. چه چیزی ساخته شد

| # | مورد | فایل |
| --- | --- | --- |
| ۱ | جدول کدهای یک‌بارمصرف + تنظیمات OTP | `application/migrations/074_create_otp_codes_table.php` |
| ۲ | سرویس OTP (تولید، هش، اعتبارسنجی، محدودیت نرخ) | `application/libraries/Otp_service.php` |
| ۳ | کنترلر پنل مشتری (ورود، لیست نوبت‌ها، لغو، خروج) | `application/controllers/Customer.php` |
| ۴ | قالب پنل مشتری | `application/views/layouts/customer_portal_layout.php` |
| ۵ | صفحهٔ پنل مشتری | `application/views/pages/customer_portal.php` |
| ۶ | کلاینت HTTP و اسکریپت صفحه | `assets/js/http/customer_portal_http_client.js`, `assets/js/pages/customer_portal.js` |
| ۷ | استایل | `assets/css/customer_portal.scss` |
| ۸ | ترجمه‌ها (فارسی و انگلیسی) | `application/language/{persian,english}/customer_portal_lang.php` |
| ۹ | تست‌ها | `tests/Unit/Sms/OtpServiceTest.php` |

هیچ فایل هسته‌ای تغییر نکرده است (۰ تغییر در `CUSTOMIZATIONS.md` برای این مرحله).

## ۲. مسیرها (routes)

| متد | مسیر | توضیح |
| --- | --- | --- |
| GET | `/index.php/customer/portal` | صفحهٔ پنل مشتری (اگر `portal_enabled` صفر باشد → ۴۰۳) |
| POST | `/index.php/customer/request_otp` | درخواست ارسال کد (`phone_number`) |
| POST | `/index.php/customer/verify_otp` | تأیید کد (`phone_number`, `code`) و شروع نشست |
| GET | `/index.php/customer/appointments` | نوبت‌های پیش‌رو و گذشتهٔ کاربر وارد‌شده |
| POST | `/index.php/customer/cancel_appointment` | لغو نوبت (`appointment_id`) |
| POST | `/index.php/customer/logout` | پایان نشست |

حفاظت CSRF روی این مسیرها فعال است (برخلاف `booking/*` که در `config.php` مستثنا شده)؛ تمام درخواست‌های
POST کلید `csrf_token` را از `vars('csrf_token')` می‌فرستند.

## ۳. جدول `otp_codes`

| ستون | نوع | توضیح |
| --- | --- | --- |
| `id` | BIGINT PK | |
| `phone_number` | VARCHAR(32) | شمارهٔ نرمال‌شده (`09xxxxxxxxx`) — ایندکس‌دار |
| `purpose` | VARCHAR(32) | پیش‌فرض `portal_login` |
| `code_hash` | VARCHAR(255) | `password_hash()` کد — **کد هرگز به‌صورت متن ذخیره نمی‌شود** |
| `attempts` / `max_attempts` | INT | تعداد تلاش‌های انجام‌شده و سقف آن |
| `expires_datetime` | DATETIME | انقضای کد |
| `used_datetime` | DATETIME NULL | زمان مصرف (یا باطل‌شدن با کد جدید) |
| `ip_address` | VARCHAR(45) | برای محدودیت نرخ |
| `create_datetime` | DATETIME | ایندکس‌دار (برای شمارش پنجرهٔ نرخ) |

تنظیمات اضافه‌شده: `otp_enabled`, `otp_code_length` (۵), `otp_code_ttl_seconds` (۱۲۰),
`otp_max_attempts` (۵), `otp_max_requests_per_phone` (۳ در ۱۵ دقیقه), `otp_max_requests_per_ip`
(۱۰ در ۶۰ دقیقه), `portal_enabled`.

## ۴. قواعد امنیتی پیاده‌شده

1. فقط آخرین کد هر شماره معتبر است؛ با صدور کد جدید، کد قبلی باطل می‌شود.
2. کد بعد از مصرف (یا اتمام تلاش‌ها) دیگر قابل استفاده نیست؛ هر کد یک‌بارمصرف است.
3. تلاش‌های ناموفق شمرده می‌شوند و پس از `otp_max_attempts` کد قفل می‌شود.
4. محدودیت نرخ هم روی شمارهٔ موبایل و هم روی IP (مستقل از `rate_limit_helper.php` که در پاسخ ۴۲۹ خروج
   می‌کند — اینجا پاسخ JSON با `retry_after` برگردانده می‌شود).
5. ورودی‌ها با `check()` اعتبارسنجی می‌شوند و شمارهٔ موبایل با `is_valid_iran_mobile_number()`
   (helper مرحلهٔ ۵) بررسی می‌شود.
6. لاگ‌ها شمارهٔ موبایل را ماسک می‌کنند (`mask_phone_number()`), کد هرگز لاگ نمی‌شود.
7. پنل با `robots: noindex, nofollow` رندر می‌شود و لیست نوبت‌ها فقط برای نشست همان شماره برمی‌گردد.

## ۵. تست و شواهد (اجرای واقعی در سندباکس)

### PHPUnit

```
node dev/sandbox/run.mjs --phpunit --configuration phpunit.xml
→ OK (93 tests, 7580 assertions)   (شامل ۱۱ تست OtpService)
```

پوشش `OtpServiceTest`: تولید کد عددی با طول تنظیمات، ذخیرهٔ **فقط هش** (و اینکه کد در دیتابیس پیدا
نمی‌شود)، یک‌بارمصرف‌بودن، باطل‌شدن کد قبلی با کد جدید، شمارش تلاش‌های غلط، قفل‌شدن پس از سقف تلاش‌ها،
انقضا، محدودیت نرخ شماره و IP، رد شمارهٔ نامعتبر، حالت غیرفعال، و پاک‌سازی کدهای منقضی.

### probe واقعی روی مسیرهای پنل

| درخواست | نتیجه |
| --- | --- |
| `GET /index.php/customer/portal?language=persian` | `<html lang="fa" dir="rtl">`، عنوان «نوبت‌های من»، متن‌های فارسی (ورود به پنل مشتریان، ارسال کد تأیید، نوبت‌های پیش‌رو…) |
| `POST /customer/request_otp` با `09123456789` | `{"success":true,"phone_number":"09123456789","expires_in":120,"message":"کد تأیید پیامک شد."}` و ثبت رکورد در `otp_codes` + صف پیامک |
| محتوای پیامک در `sms_messages` | `کد ورود شما به Company Name: 98120` (driver=mock، وضعیت sent) |
| `POST /customer/verify_otp` با کد غلط | `{"success":false,"reason":"invalid_code","message":"کد وارد‌شده درست نیست."}` و افزایش `attempts` به ۱ |
| `POST /customer/verify_otp` با کد صحیح | `{"success":true,"customer":null}` و ثبت `used_datetime` (کد مصرف شد) |

## ۶. معیار پذیرش مرحله ۳

- [x] جدول `otp_codes` با migration و `down()` سالم ساخته می‌شود.
- [x] `Otp_service` با محدودیت نرخ (شماره + IP)، انقضا و سقف تلاش پیاده و تست شده است.
- [x] پنل مشتری `/index.php/customer/portal` در فارسی/RTL رندر می‌شود.
- [x] ورود با کد پیامک‌شده کار می‌کند و نشست فقط برای همان شماره معتبر است.
- [x] لغو نوبت از پنل، فقط برای نوبت‌های آیندهٔ همان مشتری و با اعتبارسنجی انتقال وضعیت.
- [ ] تست دستی روی سرور با پیامک واقعی (پس از بازگشت SSH و تنظیم کلید TextBee).

## ۷. ریسک‌ها و نکات عملیاتی

1. **پیامک واقعی نیازمند کلید TextBee است**؛ تا آن زمان `sms_driver=mock` است و پیام‌ها فقط در
   `sms_messages` و لاگ ثبت می‌شوند (هیچ پیامکی ارسال نمی‌شود).
2. کدها به‌صورت هش ذخیره می‌شوند؛ **هیچ راهی برای خواندن کد یک کاربر از دیتابیس وجود ندارد** — برای
   پشتیبانی، فقط می‌توان کد جدید صادر کرد.
3. برای پاک‌سازی دوره‌ای کدهای منقضی، `php index.php sms_maintenance cleanup_otp_codes` را در cron
   قرار دهید (مرحلهٔ ۴).
4. پنل مشتری «اولین ورود» را خودکار به حساب مشتری وصل می‌کند: اگر رکوردی با آن شماره وجود نداشته باشد،
   `customer: null` برمی‌گردد و بعد از اولین رزرو (با همان شماره) نوبت‌ها نمایش داده می‌شوند.
