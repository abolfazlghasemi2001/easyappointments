# گام ۲ — بومی‌سازی فارسی، تقویم جلالی، RTL و تعطیلات رسمی

> وضعیت: انجام شد — منتظر تأیید کارفرما برای ورود به گام ۳ (ورود با رمز یک‌بارمصرف، نقش‌ها و پیامک TextBee).

---

## ۱. خلاصه مدیریتی

در این گام، پروژه Easy!Appointments برای بازار ایران آماده شد:

- **تقویم جلالی (هجری شمسی)** برای نمایش همه تاریخ‌ها؛ داده‌ها همچنان **میلادی** ذخیره می‌شوند (سازگاری کامل با API و ساختار فعلی دیتابیس).
- **چیدمان راست‌به‌چپ (RTL)** برای زبان فارسی، همراه با تولید خودکار نسخه RTL از تمام فایل‌های استایل.
- **قلم وزیرمتن (Vazirmatn)** به‌صورت میزبانی‌شده در خود پروژه (بدون وابستگی به CDN خارجی).
- **ارقام فارسی** در نمایش تاریخ‌ها و اعداد رابط کاربری.
- **هفته از شنبه** شروع می‌شود (تقویم‌ها، انتخابگر تاریخ و جدول برنامه کاری).
- **تعطیلات رسمی ایران**: جدول جدید، مدل، صفحه مدیریت در پیشخوان و اتصال به موتور محاسبه زمان‌های آزاد (روز تعطیل = بدون نوبت).
- **منطقه زمانی نمایش** `Asia/Tehran` (با تنظیم قابل تغییر توسط مدیر) در حالی که زمان‌ها در دیتابیس دست‌نخورده باقی می‌مانند.

---

## ۲. فایل‌های جدید

| مسیر | توضیح |
| --- | --- |
| `application/libraries/Jalali_date.php` | کتابخانه تبدیل تقویم میلادی ↔ جلالی، تشخیص سال کبیسه، تعداد روزهای ماه، نام ماه/روز هفته، قالب‌بندی (`format`) و تجزیه (`parse`) بدون هیچ وابستگی خارجی. |
| `application/helpers/localization_helper.php` | توابع کمکی: `is_rtl()`، `app_text_direction()`، `jalali_calendar_enabled()`، `persian_digits_enabled()`، `jalali_date()`، `localize_date()`، `localize_date_time()`، `localize_time()`، `localize_number()`، `localize_phone()`، `localize_digits()`، `to_latin_digits()`، `safe_setting()`، `app_timezone()`، `app_asset_url()`، `app_rtl_asset_url()`، `is_valid_date_value()`. |
| `application/hooks/localization.php` | هوکِ تنظیمات بومی‌سازی؛ مقادیر `calendar_type`، `persian_digits`، `display_timezone`، `text_direction` و `is_rtl` را به `window.vars` تزریق می‌کند (بدون تغییر فایل‌های هسته). |
| `application/libraries/…` → `assets/js/utils/jalali_date.js` | نسخه جاوااسکریپت کتابخانه جلالی (`App.Utils.Jalali`) شامل `toJalali`، `toGregorian`، `format`، `parse`، `toPersianDigits`، `toLatinDigits`، `monthName`، `weekdayName`، `monthDays`، `isLeapYear`، `firstDayOfWeek`، `orderedWeekdayNames`، `weekOrder`. |
| `assets/js/utils/jalali_picker.js` | افزونه انتخابگر تاریخ جلالی روی flatpickr؛ شبکه میلادی را پنهان و شبکه جلالی را رسم می‌کند، در حالی که مقدار واقعی هم‌چنان یک `Date` میلادی است (`App.Utils.JalaliPicker`). |
| `assets/js/http/holidays_http_client.js` | کلاینت HTTP تعطیلات (`App.Http.Holidays`): `search`، `store`، `update`، `destroy`، `find`، `importOfficial`، `feed`. |
| `assets/js/pages/holidays.js` | منطق صفحه مدیریت تعطیلات (`App.Pages.Holidays`): فهرست، جست‌وجو، افزودن/ویرایش/حذف، درون‌ریزی تعطیلات رسمی و نمایش تاریخ میلادی معادل. |
| `application/models/Holidays_model.php` | مدل تعطیلات: `save`، `insert`، `update`، `delete`، `find`، `get`، `get_value`، `search`، `get_by_date`، `get_range`، `is_holiday`، `import_official_holidays`، `validate`. |
| `application/controllers/Holidays.php` | کنترلر پیشخوان: `index`، `search`، `store`، `find`، `update`، `destroy`، `import`، `feed` (همه با بررسی سطح دسترسی `PRIV_SYSTEM_SETTINGS` و CSRF). |
| `application/views/pages/holidays.php` | صفحه پیشخوان تعطیلات (سبک مشابه «دوره‌های مسدود شده»). |
| `application/data/iran_holidays.php` | داده تعطیلات رسمی ایران: تعطیلات ثابت جلالی (نوروز، ۱۲ و ۱۳ فروردین، ۱۴ و ۱۵ خرداد، ۲۲ بهمن، ۲۹ اسفند) + تعطیلات قمری سال‌های میلادی ۲۰۲۵ تا ۲۰۲۷. |
| `application/migrations/070_create_holidays_table.php` | ساخت جدول `holidays` و درون‌ریزی خودکار تعطیلات (idempotent). |
| `application/migrations/071_add_localization_settings.php` | تنظیم پیش‌فرض‌ها: `calendar_type=jalali`، `persian_digits=1`، `display_timezone=Asia/Tehran`، `default_language=persian`، `first_weekday=saturday`، `date_format=YMD`. |
| `assets/css/persian.scss` | قلم وزیرمتن (`@font-face`) و قواعد تایپوگرافی فارسی/RTL. |
| `tests/Unit/Localization/JalaliDateTest.php` | تست‌های واحد کتابخانه جلالی. |
| `tests/Unit/Localization/LocalizationHelperTest.php` | تست‌های واحد توابع کمکی بومی‌سازی. |
| `tests/Unit/Holidays/HolidaysModelTest.php` | تست‌های واحد مدل تعطیلات. |

---

## ۳. فایل‌های تغییر یافته

| مسیر | تغییر |
| --- | --- |
| `application/config/autoload.php` | افزودن helper بومی‌سازی به فهرست helper‌های خودکار. |
| `application/config/hooks.php` | ثبت هوک `load_localization_script_vars`. |
| `application/libraries/Availability.php` | روزهای تعطیل در محاسبه زمان‌های آزاد نادیده گرفته می‌شوند (`holidays_model->is_holiday()`). |
| `application/views/layouts/{booking,backend,account}_layout.php` | `dir="rtl"` بر اساس زبان، بارگذاری نسخه RTL استایل‌ها و بارگذاری `persian.css` و اسکریپت‌های جلالی. |
| `application/views/pages/calendar.php` | بارگذاری locale فارسی FullCalendar برای نمایش تاریخ‌های جلالی (از طریق `Intl` با `fa-IR`). |
| `application/views/components/settings_nav.php` | افزودن پیوند «تعطیلات» به منوی تنظیمات. |
| `assets/js/utils/ui.js` | انتخابگرهای تاریخ/تاریخ‌وزمان در حالت جلالی به `JalaliPicker` سپرده می‌شوند؛ افزودن `getDisplayedMonth()` و `isJalaliPickerEnabled()`. |
| `assets/js/utils/date.js` | `App.Utils.Date.format()` در حالت جلالی، تاریخ شمسی و ارقام فارسی برمی‌گرداند (مقدار ورودی/خروجی همچنان میلادی است). |
| `assets/js/utils/calendar_{default,table}_view.js` | شروع هفته از `App.Utils.Jalali.firstDayOfWeek()` (شنبه). |
| `assets/js/pages/booking.js` | تشخیص ماه نمایش‌داده‌شده از طریق `App.Utils.UI.getDisplayedMonth()` (سازگار با تقویم جلالی). |
| `assets/js/utils/jalali_picker.js` | انتشار ماه نمایش‌داده‌شده روی instance و فراخوانی `onMonthChange` هنگام ناوبری جلالی. |
| `gulpfile.js` / `package.json` / `babel.config.json` | افزودن `vazirmatn`، تولید خودکار استایل‌های RTL (`styles:rtl` با `postcss-rtlcss`)، کپی قلم وزیرمتن و locale فارسی FullCalendar؛ جایگزینی بسته رهاشده `babel-preset-minify` با `gulp-terser` (سازگاری با Babel 8). |
| تمام فایل‌های `application/language/*/translations_lang.php` | افزودن ۱۵ کلید ترجمه جدید مربوط به تعطیلات (فارسی ترجمه‌شده، سایر زبان‌ها با متن انگلیسی). |

---

## ۴. تغییرات هسته (Core) و توجیه آن‌ها

طبق الزام پروژه، تا حد امکان از فایل‌های هسته فاصله گرفته شده است. تغییرات زیر لازم بودند و به‌صورت حداقلی انجام شدند:

1. **`application/libraries/Availability.php`** — دو خط: بارگذاری `holidays_model` و بازگشت آرایه خالی برای روزهای تعطیل. بدون این تغییر، تعطیلات رسمی هیچ اثری بر نوبت‌دهی نداشتند.
2. **`application/views/layouts/*.php`** — افزودن `dir` و لینک استایل RTL/قلم فارسی. راهی برای اعمال RTL بدون ویرایش layout وجود ندارد.
3. **`application/config/{autoload,hooks}.php`** — افزودن helper و هوک. این فایل‌ها «تنظیمات» هستند، نه منطق هسته، و الگوی رسمی توسعه CI3 محسوب می‌شوند.
4. **`application/views/components/settings_nav.php`** — افزودن آیتم منو برای صفحه تعطیلات.
5. **`application/views/pages/calendar.php`** — افزودن یک خط بارگذاری locale فارسی FullCalendar (فقط در حالت RTL).

فایل‌های هسته CodeIgniter (`system/`) و `application/core/EA_Controller.php` **هیچ تغییری نکرده‌اند**؛ به‌جای آن از هوک `post_controller_constructor` استفاده شد.

---

## ۵. نحوه ساخت و اجرا

```bash
# ۱. نصب وابستگی‌های PHP (در محیط سندباکس از dev/sandbox/minicomposer.py استفاده شد)
composer install

# ۲. نصب وابستگی‌های جاوااسکریپت
npm install

# ۳. ساخت دارایی‌ها: vendor + فشرده‌سازی JS + کامپایل SCSS + تولید استایل‌های RTL
npx gulp vendor
npx gulp scripts
npx gulp styles
npx gulp styles:rtl

# یا همه با هم
npx gulp compile
```

> نکته: پس از هر تغییر در `assets/css/**/*.scss` باید `gulp styles` و سپس `gulp styles:rtl` اجرا شود تا نسخه راست‌به‌چپ نیز به‌روز شود.

سپس برنامه از مسیر معمول خود (Apache/nginx یا `php -S`) اجرا می‌شود. نخستین اجرا مهاجرت‌های ۰۷۰ و ۰۷۱ را اعمال می‌کند و تعطیلات رسمی سال جاری و سال بعد را درون‌ریزی می‌کند.

---

## ۶. آزمون‌ها و نتایج

| آزمون | فرمان | نتیجه |
| --- | --- | --- |
| تست‌های واحد PHP (کل مجموعه) | `node dev/sandbox/run.mjs --phpunit --configuration phpunit.xml` | `OK (36 tests, 7400 assertions)` |
| کتابخانه جلالی (PHP) | فیلتر `JalaliDateTest` | ۱۱ تست: تبدیل نمونه‌های شناخته‌شده، رفت‌وبرگشت ۲۰ سال (روزبه‌روز)، کبیسه‌ها (۱۴۰۳ و ۱۴۰۸)، تعداد روز ماه‌ها، فرمت‌ها، ارقام فارسی، `parse`، نام روز هفته |
| توابع کمکی بومی‌سازی | فیلتر `LocalizationHelperTest` | ۸ تست: تشخیص RTL، جهت متن، ارقام فارسی، اعتبارسنجی تاریخ، منطقه زمانی |
| مدل تعطیلات | فیلتر `HolidaysModelTest` | ۱۰ تست: ذخیره/ویرایش، رد تاریخ نامعتبر، تعطیلات تکرارشونده (همان روز جلالی در سال بعد)، محدوده تاریخ، درون‌ریزی تکراری (idempotent)، جست‌وجو |
| بررسی صحت نحوی PHP | `node dev/sandbox/run.mjs --lint` | همه فایل‌های تغییریافته OK |
| بررسی صحت نحوی JS | `node -e "new Function(fs.readFileSync(...))"` روی همه فایل‌های تغییر‌یافته | OK |
| اجرای واقعی برنامه | درخواست HTTP به صفحه رزرو، ورود مدیر، صفحه تعطیلات، جست‌وجو و درون‌ریزی | `200` و بدون خطای PHP |
| اثر تعطیلات بر زمان‌های آزاد | درخواست به موتور دسترس‌پذیری برای ۱۴۰۵/۰۱/۰۱ (تعطیل) و یک روز عادی | تعطیل: `0` بازه / روز عادی: `32` بازه |

نمونه تبدیل‌های بررسی‌شده (میلادی → جلالی):

| میلادی | جلالی |
| --- | --- |
| 2024-03-20 | 1403/01/01 |
| 2025-03-20 | 1403/12/30 |
| 2026-03-21 | 1405/01/01 |
| 2026-09-30 | 1405/07/08 |
| 2027-03-21 | 1406/01/01 |

---

## ۷. نکات مهم عملیاتی

1. **داده‌های تعطیلات قمری** بر اساس تقویم رسمی ایران برای سال‌های میلادی ۲۰۲۵ تا ۲۰۲۷ گردآوری شده‌اند. از آن‌جا که تاریخ تعطیلات قمری به رؤیت هلال بستگی دارد و ممکن است یک روز جابه‌جا شود، مدیر می‌تواند همه رکوردها را از صفحه «تعطیلات» ویرایش یا حذف کند. برای سال‌های بعد کافی است آرایه `lunar` در `application/data/iran_holidays.php` تکمیل شود.
2. **ذخیره‌سازی میلادی/UTC** تغییری نکرده است؛ همه تاریخ‌های دیتابیس میلادی هستند و فقط نمایش جلالی می‌شود. این تصمیم سازگاری کامل با REST API و مهاجرت‌های آینده را تضمین می‌کند.
3. **منطقه زمانی نمایش** از تنظیم `display_timezone` خوانده می‌شود (پیش‌فرض `Asia/Tehran`).
4. **تعطیلات قابل تنظیم** هستند: صفحه «تنظیمات → تعطیلات» امکان افزودن/ویرایش/حذف و درون‌ریزی تعطیلات رسمی سال جلالی را می‌دهد.
5. در محیط سندباکس، مرورگر/داکر در دسترس نبود؛ بنابراین اجرای واقعی با سرور PHP (php-wasm) و درخواست‌های HTTP آزمایش شد. ارزیابی ظاهری نهایی (فونت و RTL) باید توسط کاربر روی مرورگر خود انجام شود.
