# گام ۲ (اصلاحی) — فعال‌سازی واقعی تقویم جلالی

> وضعیت: انجام شد و با تست خودکار تأیید شد. این سند دلیل فنی «چرا تقویم شمسی نمایش داده نمی‌شد» را ثبت می‌کند.

---

## ۱. خلاصه

تقویم جلالی در گام ۲ پیاده‌سازی شده بود، اما **دو باگ واقعی** باعث می‌شد در صفحه رزرو کار نکند. هر دو با اجرای واقعی کد
(jsdom در سندباکس) بازتولید، رفع و تست شدند.

| # | باگ | اثر روی کاربر | رفع |
| --- | --- | --- | --- |
| ۱ | `$calendar.find('.flatpickr-calendar').append($wrapper)` در `jalali_picker.js` — ظرف تقویم **خودش** همان عنصر `.flatpickr-calendar` است، پس `find()` همیشه خالی برمی‌گشت | شبکه جلالی هرگز به DOM اضافه نمی‌شد، در حالی که شبکه میلادی پنهان می‌شد → تقویم خالی/میلادی | `$calendar.append($wrapper)` |
| ۲ | `parseValue()` ابتدا مقدار را جلالی تفسیر می‌کرد | هر مقدار میلادیِ خود برنامه (`2026-09-30`) به‌عنوان سال جلالی ۲۰۲۶ خوانده می‌شد → انحراف **+۶۲۱ سال** در مقادیر پیش‌پر و `minDate`/`maxDate` | تشخیص مقدار ISO میلادی با آستانه سال ≥ ۱۷۰۰، سپس جلالی، و در نهایت پارسر پیش‌فرض flatpickr |

بهبود کنار آن: قالب ورودی/خروجی تقویم حالا از تنظیم `date_format` پیروی می‌کند (`YMD`/`DMY`/`MDY`) و ارقام فارسی در
همه حالت‌ها رعایت می‌شود.

---

## ۲. فایل‌های تغییر‌یافته

| مسیر | تغییر |
| --- | --- |
| `assets/js/utils/jalali_picker.js` | رفع دو باگ + توابع کمکی `inputDatePattern()`، `isGregorianIsoValue()`، `reorderJalaliValue()` |
| `tests/js/jalali_picker.test.mjs` | **جدید** — ۲۳ تست jsdom روی فایل‌های واقعی پروژه |
| `package.json` / `package-lock.json` | افزودن `jsdom` به devDependencies و اسکریپت `npm run test:js` |
| `docs/fa/step-02-jalali-fix.md` | **جدید** — همین سند |

هیچ فایل PHP و هیچ فایل هسته‌ای تغییر نکرد.

---

## ۳. نحوه تست (معیار پذیرش)

```bash
npm install                 # وابستگی‌ها (jsdom، jquery، flatpickr، moment)
npm run test:js             # ۲۳ تست تقویم جلالی
node dev/sandbox/run.mjs --phpunit --configuration phpunit.xml   # ۳۶ تست PHP
npx gulp compile            # ساخت assets/js/**/*.min.js و assets/css/**/*.rtl.css
```

نتیجه واقعی اجرا در سندباکس:

```
23 passed, 0 failed      (tests/js/jalali_picker.test.mjs)
OK (36 tests, 7400 assertions)   (PHPUnit)
```

پوشش تست‌های JS:

| گروه | Assertion‌ها |
| --- | --- |
| نصب جلالی | اتصال `.ea-jalali-wrapper` به تقویم · پنهان‌بودن شبکه میلادی · عنوان «مهر ۱۴۰۵» · ۶ سطر هفته · ستون اول «ش» · ۴ خانه خالی ابتدای مهر (۱۴۰۵/۰۷/۰۱ چهارشنبه است) · قرارگرفتن روز ۱ در خانه درست · نمایش روز انتخابی «۸» · مقدار ورودی `۱۴۰۵/۰۷/۰۸` |
| ذخیره میلادی | کلیک روی روز ۲۱ → تاریخ میلادی **۲۰۲۶** (نه ۲۶۴۷) و شکل `YYYY-MM-DD` برای API |
| مقادیر ISO میلادی | `setDate('2026-09-30')` → سال ۲۰۲۶ · نمایش `۱۴۰۵/۰۷/۰۸` · تاریخ‌وزمان ISO |
| ورودی کاربر | پارس `۱۴۰۵/۰۷/۰۸` با ارقام فارسی · ترتیب `DMY` → `۰۸/۰۷/۱۴۰۵` · ترتیب `MDY` → `۰۷/۰۸/۱۴۰۵` |
| تاریخ‌وزمان و بازه | `۱۴۰۵/۰۷/۰۸ ۲:۳۰ pm` · غیرفعال‌شدن روزهای خارج از `minDate`/`maxDate` |
| نصب میلادی (رگرسیون) | نبود wrapper · نمایان‌بودن شبکه میلادی · مقدار `2026/09/30` |

بررسی سمت PHP (اجرای واقعی برنامه با php-wasm و درخواست صفحه رزرو با `?language=persian`):

```html
<html lang="fa" dir="rtl">
<link rel="stylesheet" href=".../assets/css/persian.css">
<script src=".../assets/js/utils/jalali_date.js"></script>
<script src=".../assets/js/utils/jalali_picker.js"></script>
window.vars = { "language":"persian", "language_code":"fa", "calendar_type":"jalali",
                "persian_digits":"1", "display_timezone":"Asia/Tehran", "is_rtl":true, ... }
```

یعنی هوک بومی‌سازی، تنظیمات RTL و بارگذاری اسکریپت‌های جلالی همه درست کار می‌کنند و مشکل صرفاً در خود پیکر بود.

---

## ۴. نکات عملیاتی برای سرور واقعی

1. **پس از هر `git pull` حتماً `npx gulp compile` اجرا شود.** الگوی `asset_url()` در حالت production فایل‌ها را به
   `*.min.js` تبدیل می‌کند؛ اگر بیلد قدیمی باشد فایل `assets/js/utils/jalali_picker.min.js` وجود ندارد و مرورگر ۴۰۴
   می‌گیرد (تقویم به حالت میلادی برمی‌گردد). در گام ۱ یک اسکریپت `scripts/build-assets.sh` و یک health-check برای
   تشخیص همین حالت اضافه می‌شود.
2. حافظه کش مرورگر: پارامتر `cache_busting_token` به‌صورت خودکار به URL دارایی‌ها اضافه می‌شود، بنابراین نیازی به
   hard-refresh نیست.
3. ارزیابی چشمی نهایی روی مرورگر واقعی (رنگ‌بندی، اندازه قلم وزیرمتن، فاصله‌ها) هنوز توسط کارفرما انجام نشده است.
