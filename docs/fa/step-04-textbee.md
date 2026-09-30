# مرحله ۴ — کلاینت TextBee، صف پیامک، لاگ و حالت mock

> همهٔ کارها **فقط کد و فایل** است؛ هیچ دستوری روی سرور اجرا نشده. تست‌ها در سندباکس اجرا شده‌اند و
> خروجی واقعی‌شان در همین سند آمده است. هیچ فایل هسته‌ای تغییر نکرده است.

## ۱. معماری

```
Otp_service ─┐
             ├─► Sms_client ──► Sms_provider_interface ──┬─► Sms_provider_textbee  (پیامک واقعی)
هر جای دیگر ─┘        │                                 └─► Sms_provider_mock     (پیش‌فرض/تست)
                      ▼
              جدول sms_messages (صف + لاگ)
                      ▲
                      └── Sms_maintenance::process_queue (cron) → تلاش دوباره با backoff
```

| فایل | نقش |
| --- | --- |
| `application/libraries/Sms_provider_interface.php` | قرارداد `send()` / `name()` + کلاس خطای `Sms_exception` (با پرچم `is_transient()`) |
| `application/libraries/Sms_provider_textbee.php` | کلاینت واقعی TextBee با timeout، تلاش دوباره و backoff نمایی + jitter |
| `application/libraries/Sms_provider_mock.php` | درایور پیش‌فرض؛ هیچ پیامکی نمی‌فرستد، در لاگ و حافظه ثبت می‌کند |
| `application/libraries/Sms_client.php` | انتخاب درایور، صف‌گذاری، ارسال، لاگ، پردازش صف |
| `application/controllers/Sms_maintenance.php` | `process_queue` و `cleanup_otp_codes` برای cron |
| `application/migrations/075_create_sms_messages_table.php` | جدول `sms_messages` + تنظیمات SMS |
| `.env.example` | کلیدهای محیطی (بدون هیچ مقدار واقعی) |

## ۲. درخواست TextBee (مطابق مستندات رسمی)

```
POST {TEXTBEE_BASE_URL}/gateway/send-sms
x-api-key: {TEXTBEE_API_KEY}
Content-Type: application/json

{"recipients": ["+989123456789"], "message": "کد ورود شما: 12345"}
```

- موفقیت: `200` و بدنهٔ `{"data":{"smsBatchId":"..."}}` → شناسهٔ پیام ذخیره می‌شود
  (کلیدهای جایگزین `messageId`, `id`, `_id` هم پذیرفته می‌شوند).
- `429` (سقف پلن/انفجار درخواست)، `408`, `425`, `5xx` و خطاهای شبکه → **قابل‌تلاش‌دوباره** با
  backoff نمایی: ۱s، ۲s، ۴s (+ jitter تا ۲۵۰ms).
- `400`, `401`, `403`, `404` → خطای دائمی؛ تلاش دوباره بی‌فایده است و پیام در وضعیت `failed` ثبت می‌شود.
- شماره‌ها به E.164 تبدیل می‌شوند (`09xxxxxxxxx` → `+989xxxxxxxxx`) و همیشه نرمال‌سازی ارقام فارسی/عربی
  انجام می‌شود.
- **کلید API هرگز در پیام خطا یا لاگ ظاهر نمی‌شود** (`mask_secrets()` و تست اختصاصی برای آن).

## ۳. جدول `sms_messages` (صف + لاگ)

`id`, `phone_number`, `message`, `context` (`otp`, `waitlist`, …), `reference_id`, `driver`, `status`
(`queued`/`sent`/`failed`), `attempts`, `max_attempts`, `next_attempt_datetime`, `provider_message_id`,
`error`, `create_datetime`, `sent_datetime`, `update_datetime` — با ایندکس روی شماره، وضعیت، context و تاریخ.

قواعد صف:

- `dispatch()` پیام را اول در جدول ثبت می‌کند و بعد بلافاصله تلاش می‌کند (خطای ارسال باعث از دست رفتن
  درخواست کاربر نمی‌شود).
- شکست موقت → وضعیت `queued` با `next_attempt_datetime` بر اساس تأخیرهای ۱، ۵، ۱۵ و ۶۰ دقیقه.
- شکست دائمی یا اتمام تلاش‌ها → وضعیت `failed` با متن خطا.
- تنظیمات: `sms_enabled`, `sms_driver` (`mock` یا `textbee`), `sms_max_attempts` (۳), `sms_sender_name`.

## ۴. نصب و راه‌اندازی (روی سرور، پس از بازگشت SSH — اجرا نشده)

```bash
# ۱) فایل محیطی (هرگز در گیت commit نمی‌شود)
cd /var/www/easyappointments
cp .env.example .env && nano .env
#   SMS_DRIVER=textbee
#   TEXTBEE_API_KEY=...        (از پنل textbee.dev)
#   TEXTBEE_DEVICE_ID=...
#   SMS_MAINTENANCE_KEY=<یک رشتهٔ تصادفی طولانی>

# ۲) اجرای migration
php index.php console migrate          # 075 اجرا می‌شود

# ۳) cron (پردازش صف و پاک‌سازی کدها)
* * * * * cd /var/www/easyappointments && php index.php sms_maintenance process_queue >/dev/null 2>&1
17 4 * * * cd /var/www/easyappointments && php index.php sms_maintenance cleanup_otp_codes >/dev/null 2>&1
```

اگر سرور cron ندارد، همان دو عملیات از طریق HTTP هم قابل فراخوانی است:

```bash
curl -fsS "https://nobat.hoosna1402.ir/index.php/sms_maintenance/process_queue?key=$SMS_MAINTENANCE_KEY"
```

> اگر `SMS_MAINTENANCE_KEY` تنظیم نشده باشد، فقط درخواست‌های `127.0.0.1`/`::1` (و CLI) پذیرفته می‌شوند.

## ۵. تست‌ها و شواهد (اجرای واقعی در سندباکس)

```
node dev/sandbox/run.mjs --phpunit --configuration phpunit.xml
→ OK (93 tests, 7580 assertions)
```

- `SmsProviderTextbeeTest` (۹ تست): ساخت درخواست دقیق (URL، هدر `x-api-key`، بدنهٔ recipients/message)،
  تبدیل شماره‌ها به E.164، تلاش دوباره روی ۵۰۳ و ۴۲۹ با تأخیرهای درست، تسلیم پس از `max_attempts`،
  **عدم** تلاش دوباره روی ۴۰۱، پنهان‌ماندن کلید API در پیام خطا، رد شمارهٔ نامعتبر، خطای نبود اعتبارنامه،
  و پشتیبانی از آدرس دلخواه (`TEXTBEE_SEND_URL`) برای نصب‌های self-hosted. حمل‌ونقل HTTP در تست‌ها با یک
  تابع جعلی جایگزین می‌شود، پس **هیچ درخواست شبکه‌ای واقعی در تست‌ها زده نمی‌شود**.
- `SmsQueueTest` (۶ تست): ثبت و ارسال فوری، ماندن پیام در صف پس از خطای موقت (و ارسال موفق در اجرای
  بعدی cron)، `failed` شدن پس از اتمام تلاش‌ها، افزایش تأخیرهای تلاش دوباره، رد گیرندهٔ نامعتبر، و
  جست‌وجو در لاگ.

## ۶. معیار پذیرش مرحله ۴

- [x] کلاینت کامل TextBee با timeout، retry/backoff و تفکیک خطای موقت/دائمی.
- [x] صف پیامک روی دیتابیس (بدون نیاز به Redis) + لاگ کامل هر پیام.
- [x] حالت mock پیش‌فرض، پس برنامه بدون کلید هم کار می‌کند و هیچ پیامکی نمی‌فرستد.
- [x] کلیدها فقط از `.env` خوانده می‌شوند؛ `.env` در `.gitignore` است و `.env.example` فقط کلید خالی دارد.
- [ ] تست واقعی ارسال پیامک با کلید TextBee روی سرور (پس از بازگشت SSH).

## ۷. ریسک‌ها

1. **مصرف اعتبار/سقف پلن:** هر کد ورود یک پیامک است. محدودیت نرخ مرحلهٔ ۳ (۳ پیامک در ۱۵ دقیقه برای هر
   شماره و ۱۰ در ساعت برای هر IP) جلوی سوءاستفاده را می‌گیرد، اما در روزهای پرترافیک باید سقف پلن
   TextBee بررسی شود.
2. ارسال با گوشی اندرویدی انجام می‌شود؛ اگر گوشی خاموش یا بدون اینترنت باشد، پیام‌ها در صف می‌مانند و
   با اجرای بعدی cron ارسال می‌شوند (حداکثر `sms_max_attempts` بار).
3. در حالت `mock` پیامک واقعی ارسال نمی‌شود؛ برای محیط تولید حتماً `SMS_DRIVER=textbee` تنظیم شود.
