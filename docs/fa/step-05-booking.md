# مرحله ۵ — منطق رزرو: جلوگیری از رزرو تکراری، hold ده‌دقیقه‌ای، ماشین وضعیت و لیست انتظار

> این سند فقط تغییرات کد است. **هیچ دستوری روی سرور اجرا نشده** (SSH قطع است). همهٔ تست‌ها در سندباکس
> با sqlite و PHPUnit اجرا شده‌اند و خروجی واقعی آن‌ها در همین سند آمده است.

## ۱. خلاصهٔ مسئله

مسیر عمومی رزرو (`POST /index.php/booking/register`) پیش از این:

- تابع `Appointments_model::has_provider_conflict()` را **صدا نمی‌زد** (این تابع فقط در `Calendar.php:332`
  استفاده می‌شد)، یعنی بررسی نهایی تداخل در مسیر عمومی وجود نداشت؛
- قید یکتای دیتابیسی نداشت، پس دو درخواست هم‌زمان می‌توانستند هر دو از بررسی availability بگذرند و
  **دو نوبت روی یک بازهٔ زمانی** ثبت شود؛
- نه hold ده‌دقیقه‌ای داشت، نه ماشین وضعیت، نه لیست انتظار.

## ۲. راه‌حل پیاده‌شده

### ۲.۱ بررسی تداخل + ذخیرهٔ تراکنشی — `application/libraries/Booking_service.php` (فایل جدید)

- `has_blocking_appointment()`: پرس‌وجوی تداخل روی `appointments` با دو شرط مهم:
  - نوبت‌های آزادکننده (`status = Cancelled` یا `Draft`) نادیده گرفته می‌شوند؛
  - hold منقضی‌شده (`status = Pending` و `hold_expires_datetime` گذشته) هم نادیده گرفته می‌شود.
  - همین قاعده در `Availability` هم اعمال شد تا «ساعت‌های نمایش‌داده‌شده» و «ساعت‌های قابل‌ثبت» یکی باشند.
- `assert_slot_is_free()`: اگر تداخل باشد `RuntimeException` با پیام فارسی `requested_hour_is_unavailable`.
- `save_appointment()`: ذخیره داخل تراکنش + `db_debug` موقتاً خاموش؛ اگر کلید یکتا برخورد کند یا خطای
  دیتابیس رخ دهد، تراکنش rollback و همان پیام «ساعت در دسترس نیست» برگردانده می‌شود (نه خطای مرگ‌بار صفحه).
- `apply_initial_state()`: وضعیت اولیه را تعیین می‌کند و در صورت `Pending`/`hold` مقدار
  `hold_expires_datetime = now + booking_hold_minutes` می‌گذارد. (درخواست عمومی اجازه ندارد نوبت را
  مستقیماً در وضعیت نهایی `Completed/Cancelled/No-show` بسازد.)
- `release_expired_holds()`: نوبت‌های Pending با hold گذشته را به `Cancelled` می‌برد (آزادسازی اسلات).
- `confirm_hold()`: تبدیل hold به نوبت عادی (برای مرحلهٔ ۶ — زرین‌پال).

### ۲.۲ ماشین وضعیت — `application/libraries/Appointment_status.php` (فایل جدید)

`Draft → Pending → Booked → Confirmed → Completed / Cancelled / No-show` با `Rescheduled` به‌عنوان حالت
میانی. انتقال‌های مجاز در `TRANSITIONS` تعریف شده‌اند؛ وضعیت‌های `Completed/Cancelled/No-show` نهایی‌اند و
`assert_transition()` در صورت تلاش برای تغییر غیرمجاز استثنا می‌دهد. متد `releases_slot()` تعیین می‌کند که یک
نوبت هنوز اسلاتش را اشغال می‌کند یا نه.

> نکته: کد هستهٔ فعلی هیچ‌جا `status = 'Cancelled'` نمی‌گذارد (لغو عمومی، رکورد را حذف می‌کند). این وضعیت
> توسط همین مرحله (آزادسازی hold) و مراحل بعدی (پرداخت/پنل مدیریت) استفاده می‌شود.

### ۲.۳ اتصال به مسیر عمومی — `application/controllers/Booking.php` (**تنها تغییر هسته، با اجازهٔ صریح شما**)

در `register()`، سه خط پیشین:

```php
$appointment['status'] = $appointment_status_options[0] ?? null;
$appointment['end_datetime'] = $this->appointments_model->calculate_end_datetime($appointment);
$this->appointments_model->only($appointment, $this->allowed_appointment_fields);
$appointment_id = $this->appointments_model->save($appointment);
```

جایگزین شد با:

```php
$appointment['status'] = $appointment_status_options[0] ?? Appointment_status::INITIAL;
$appointment['end_datetime'] = $this->appointments_model->calculate_end_datetime($appointment);
$this->appointments_model->only($appointment, $this->allowed_appointment_fields);

// FORK: booking integrity ...
$this->booking_service->apply_initial_state($appointment);
$appointment_id = $this->booking_service->save_appointment($appointment);
```

### ۲.۴ آزادسازی hold بدون دسترسی سرور — `application/controllers/Booking_maintenance.php`

نوبت‌های Pending که hold آن‌ها منقضی شده باید مرتب آزاد شوند، وگرنه اسلات برای همیشه قفل می‌ماند.
این کنترلر دو راه اجرا دارد:

```bash
# ۱) بدون HTTP، مستقیم روی سرور (توصیه‌شده)
php index.php booking_maintenance release_expired_holds

# ۲) با HTTP (برای cron از داخل شبکه/loopback یا با کلید)
curl -fsS "https://nobat.hoosna1402.ir/index.php/booking_maintenance/release_expired_holds?key=$BOOKING_MAINTENANCE_KEY"
```

- اگر کلید `BOOKING_MAINTENANCE_KEY` در `.env` (یا setting با نام `booking_maintenance_key`) تنظیم شده باشد،
  درخواست باید همان کلید را بفرستد.
- اگر کلید تنظیم نشده باشد، فقط درخواست از `127.0.0.1`/`::1` و فراخوانی CLI پذیرفته می‌شود.
- خروجی: `{"released":<count>,"datetime":"..."}`

نمونهٔ cron (روی سرور، یک‌بار در دقیقه — این دستور **اجرا نشده** و فقط برای زمان بازگشت SSH است):

```cron
* * * * * cd /var/www/easyappointments && php index.php booking_maintenance release_expired_holds >/dev/null 2>&1
```

### ۲.۵ قید یکتای دیتابیس — `application/migrations/072_add_booking_integrity.php`

- ستون `hold_expires_datetime` (DATETIME, NULL) به `appointments` اضافه می‌کند.
- setting های `booking_hold_minutes` (پیش‌فرض `10`) و `booking_unique_index_variant` را می‌سازد.
- به `appointment_status_options` مقادیر `Completed` و `No-show` را **اضافه** می‌کند (مقادیر موجود دست‌نخورده).
- ایندکس یکتا با **اولویت نسخهٔ expression**:

```sql
CREATE UNIQUE INDEX uniq_provider_active_start
ON ea_appointments ((CASE WHEN status IN ('Cancelled', 'Draft') THEN NULL ELSE start_datetime END), id_users_provider);
```

  دلیل: رکورد لغوشده نباید جلوی رزرو مجدد همان ساعت را بگیرد (چون `Cancelled` مقدار `NULL` می‌گیرد و
  ایندکس یکتا چند `NULL` را مجاز می‌داند). MySQL از 8.0.13 (functional key parts) و SQLite از 3.9
  (expression index) این را پشتیبانی می‌کنند.
- اگر موتور دیتابیس این را پشتیبانی نکند (MySQL 5.7 / MariaDB)، migration به ایندکس سادهٔ
  `uniq_provider_start (id_users_provider, start_datetime)` برمی‌گردد و مقدار
  `booking_unique_index_variant = plain` را ثبت می‌کند. در این حالت **رکوردهای لغوشده تا زمان حذف، اسلات را
  قفل نگه می‌دارند** (محدودیت ذاتی دیتابیس؛ در سند/لاگ هم هشدار داده می‌شود).
- پیش از ساخت ایندکس، رکوردهای متداخل فعلی بررسی می‌شوند؛ اگر وجود داشته باشند migration با پیام مشخص
  (و شناسهٔ provider های متداخل) **متوقف** می‌شود تا ابتدا پاک‌سازی شوند.

### ۲.۶ لیست انتظار — `application/migrations/073_create_waitlist_table.php` + `application/models/Waitlist_model.php`

جدول `waitlist` با ستون‌های: `id`, `id_users_provider`, `id_services`, `first_name`, `last_name`, `email`,
`phone_number`, `desired_date`, `desired_time_from`, `desired_time_to`, `status`, `notes`, `notified_datetime`,
`create_datetime`, `update_datetime` (ایندکس روی provider، تلفن و تاریخ). setting `waitlist_enabled`.

مدل: `save/validate/find/get/search/delete/next_in_line/mark_notified` با اعتبارسنجی موبایل ایران.
`next_in_line()` اولین نفر در صف را برمی‌گرداند (وضعیت `waiting`, تاریخ ≤ تاریخ درخواستی، در صورت نیاز همان خدمت).

اتصال UI پنل مدیریت و پیامک اطلاع‌رسانی در مرحلهٔ ۸ (پنل) و مرحلهٔ ۴ (صف پیامک) انجام می‌شود.

### ۲.۷ helper تلفن ایران — `application/helpers/phone_helper.php` (فایل جدید)

`normalize_iran_phone_number()`, `is_valid_iran_phone_number()`, `is_valid_iran_mobile_number()`,
`mask_phone_number()` — با پشتیبانی از ارقام فارسی/عربی، `+98`، `0098` و `9xxxxxxxxx`.

## ۳. فایل‌های تغییر یافته و جدید

| فایل | نوع |
| --- | --- |
| `application/controllers/Booking.php` | **تغییر هسته** (مجاز، ۴ خط + کامنت `FORK:`) |
| `application/libraries/Availability.php` | **تغییر هسته** (فیلتر ۵ خطی نوبت‌های آزادشده) |
| `application/config/autoload.php` | **تغییر هسته** (افزودن helper `phone`، یک خط) |
| `application/libraries/Booking_service.php` | جدید |
| `application/libraries/Appointment_status.php` | جدید |
| `application/controllers/Booking_maintenance.php` | جدید |
| `application/helpers/phone_helper.php` | جدید |
| `application/models/Waitlist_model.php` | جدید |
| `application/migrations/072_add_booking_integrity.php` | جدید |
| `application/migrations/073_create_waitlist_table.php` | جدید |
| `tests/Unit/Booking/AppointmentStatusTest.php` | جدید (۹ تست) |
| `tests/Unit/Booking/BookingServiceTest.php` | جدید (۹ تست) |
| `tests/Unit/Booking/WaitlistModelTest.php` | جدید (۸ تست) |
| `tests/Unit/Helper/PhoneHelperTest.php` | جدید (۳ تست) |
| `docs/fa/step-05-booking.md` | این سند |
| `CUSTOMIZATIONS.md` | به‌روزرسانی جدول تغییرات هسته |

## ۴. اجرا و rollback

```bash
# اجرا (روی سرور، پس از بکاپ دیتابیس)
php index.php console migrate            # توجه: «migrate up» فقط برای ارتقای یک‌پله‌ای است

# بازگشت (rollback)
php index.php console migrate down       # 073 → 072
php index.php console migrate down       # 072 → 071
```

> **هشدار عملیاتی:** `php index.php console migrate up` در این پروژه شمارهٔ `current + 1` را هدف می‌گیرد و
> اگر دیتابیس به‌روز باشد با خطای `No migration could be found with version number: 0NN` تمام می‌شود.
> برای رساندن دیتابیس به آخرین نسخه، `php index.php console migrate` (بدون `up`) را اجرا کنید.

**قبل از migration روی سرور بکاپ بگیرید** (دیتابیس، نه فایل‌ها) و خروجی زیر را ذخیره کنید:

```bash
mysqldump -u <user> -p <db> ea_appointments ea_settings > /root/backup-before-step5.sql
```

## ۵. تست‌ها و شواهد واقعی (سندباکس)

### PHPUnit

```
node dev/sandbox/run.mjs --phpunit --configuration phpunit.xml
→ OK (66 tests, 7484 assertions)  — PHP 8.4.25
```

پوشش جدید مرحلهٔ ۵:

- `Appointment_status`: وضعیت اولیه، نرمال‌سازی حالت‌ناوابسته، مسیر مجاز، وضعیت‌های نهایی، انتقال غیرمجاز،
  آزادسازی اسلات توسط `Cancelled` و hold منقضی.
- `Booking_service`: ذخیرهٔ نوبت، رد تداخل، امکان رزرو همان ساعت برای provider دیگر، رد نوبت روی hold فعال،
  آزادسازی hold منقضی، آزادشدن اسلات لغوشده و **رزرو مجدد همان ساعت**، رد درخواست در سطح دیتابیس،
  نرمال‌سازی وضعیت ورودی عمومی.
- `Waitlist_model`: ذخیره/بازیابی، نرمال‌سازی موبایل، رد تلفن و ایمیل نامعتبر، ترتیب صف، `mark_notified`،
  وضعیت نامعتبر.
- `phone_helper`: نرمال‌سازی (`+98`, `0098`, ارقام فارسی، فاصله)، اعتبارسنجی موبایل/ثابت، ماسک لاگ.

### migration روی دیتابیس توسعه (sqlite)

```
php index.php console migrate      → migrations = 73 ، ایندکس uniq_provider_active_start ساخته شد،
                                     booking_unique_index_variant = expression
php index.php console migrate down → 73 → 72 (حذف جدول waitlist و setting مربوطه)
php index.php console migrate down → 72 → 71 (حذف ایندکس، ستون hold_expires_datetime و setting ها)
php index.php console migrate      → بازگشت کامل به 73
```

### probe های واقعی روی مسیر عمومی رزرو (فریم‌ورک، بدون وب‌سرور)

| سناریو | نتیجه |
| --- | --- |
| `POST /index.php/booking/register` روی ساعت آزاد | `{"appointment_id":49,"appointment_hash":"..."}` |
| همان ساعت، مشتری دیگر | `{"success":false,"message":"The requested appointment is unfortunately not available. ..."}` و هیچ مشتری/نوبت دومی ساخته نشد |
| نوبت Pending با hold گذشته → `booking_maintenance/release_expired_holds` | `{"released":1,...}` و وضعیت نوبت `Cancelled` شد |
| همان ساعتِ آزادشده، مجدداً از مسیر عمومی | `{"appointment_id":60,...}` — یعنی اسلات واقعاً آزاد شد |
| `booking/get_available_hours` برای همان تاریخ | ساعت ۱۷:۰۰ در فهرست ساعت‌های آزاد برگشت (هم‌خوانی availability با ذخیره) |

## ۶. معیار پذیرش مرحله ۵

- [x] `has_provider_conflict()` (بررسی تداخل) در مسیر `Booking::register()` فعال است.
- [x] قید یکتای دیتابیسی از رزرو تکراری جلوگیری می‌کند (تست مستقیم در سطح دیتابیس + probe).
- [x] hold ده‌دقیقه‌ای (قابل تنظیم با `booking_hold_minutes`) پیاده و تست شده است؛ آزادسازی خودکار بدون
      نیاز به دسترسی دائمی به دیتابیس انجام می‌شود.
- [x] ماشین وضعیت `pending → confirmed → completed/cancelled/no-show` با تست.
- [x] لیست انتظار (جدول + مدل + تست) آماده است.
- [ ] تست دستی روی سرور (cron و رزرو واقعی) — پس از بازگشت SSH.

## ۷. ریسک‌ها و هشدارها

1. **ایندکس یکتا روی MySQL/MariaDB قدیمی:** اگر سرور MariaDB یا MySQL < 8.0.13 باشد، نسخهٔ expression ساخته
   نمی‌شود و variant = `plain` ثبت می‌شود؛ در این حالت رکورد `Cancelled` تا زمان حذف، مانع رزرو همان ساعت
   می‌شود. بعد از اجرا، مقدار `booking_unique_index_variant` را در جدول `settings` بررسی کنید:
   - `expression` → رفتار کامل؛
   - `plain` → محدودیت بالا؛ برای آزادسازی، رکوردهای لغوشده را حذف کنید (یا نسخهٔ دیتابیس را ارتقا دهید).
2. **مسیرهای مدیریتی/API تغییر نکرده‌اند:** پنل مدیریت (`Appointments.php`)، API v1 و Google/CalDAV sync هنوز
   از `appointments_model->save()` مستقیم استفاده می‌کنند. اگر از آن مسیرها نوبت متداخل ثبت شود، خطای
   دیتابیس (duplicate key) رخ می‌دهد. این کار **عمدی** است تا هسته دست‌نخورده بماند؛ بهتر است در تولید
   `db_debug` خاموش باشد (بخشی از سخت‌سازی مرحلهٔ ۱).
3. **بکاپ پیش از migration الزامی است** (بند ۴).
4. **hold فقط زمانی فعال می‌شود که نوبت با وضعیت `Pending` ثبت شود** (`apply_initial_state`). در مسیر عمومی
   فعلی وضعیت اولیه از `appointment_status_options[0]` می‌آید (`Booked`)، پس رزرو عمومی hold نمی‌گیرد؛
   مرحلهٔ ۶ (زرین‌پال) این مسیر را با `Pending` صدا می‌زند.
5. **کارایی:** پرس‌وجوی تداخل روی `(id_users_provider, start_datetime)` با ایندکس یکتا و ایندکس موجود
   `id_users_provider` پوشش داده می‌شود؛ بار اضافهٔ محسوسی انتظار نمی‌رود.
