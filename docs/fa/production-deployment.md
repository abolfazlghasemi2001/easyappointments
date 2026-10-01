# راهنمای استقرار Production با Docker و HTTPS

این راهنما فایل‌های Production جدید پروژه را توضیح می‌دهد. هیچ تغییری در DNS، فایروال، دیتابیس یا سرور زنده با اجرای این تغییرات در مخزن انجام نمی‌شود؛ اجرای استقرار باید آگاهانه روی سرور انجام شود.

## معماری

- **Caddy** تنها سرویس متصل به اینترنت است و گواهی HTTPS را به‌شکل خودکار از Let's Encrypt دریافت و تمدید می‌کند.
- **Nginx** فقط در شبکه داخلی Docker روی پورت 8080 در دسترس است و فایل‌های داخلی برنامه و `storage/` را مسدود می‌کند.
- **PHP-FPM** بر پایه PHP 8.4 است؛ وابستگی‌های Composer و فایل‌های frontend هنگام build تولید می‌شوند. Xdebug و ابزارهای توسعه در image تولید وجود ندارند.
- **MySQL** هیچ پورتی روی میزبان منتشر نمی‌کند. دادهٔ برنامه در volumeهای جدا از کد نگهداری می‌شود.

## پیش‌نیازهای شبکه

1. برای `nobat.hoosna1402.ir` رکورد `A` را به IP عمومی سرور تنظیم کنید و انتشار DNS را بررسی کنید.
2. روی فایروال سرور فقط پورت‌های `80/tcp` و `443/tcp` را برای وب باز کنید. برای HTTP/3 اختیاری، `443/udp` نیز لازم است.
3. اگر سرویس یا reverse proxy دیگری این پورت‌ها را اشغال کرده، پیش از شروع آن را برنامه‌ریزی کنید؛ Caddy نمی‌تواند هم‌زمان از پورت اشغال‌شده استفاده کند.

## پیکربندی اولیه

از ریشهٔ پروژه روی سرور:

```bash
cp .env.production.example .env
chmod 600 .env
cp config-sample.php config.php
chmod 600 config.php
```

فایل `.env` را با مقادیر واقعی و تصادفی تکمیل کنید. رمزهای MySQL را متفاوت و طولانی انتخاب کنید؛ برای ساخت مقدار تصادفی می‌توانید روی سرور از `openssl rand -hex 32` استفاده کنید. `APP_DOMAIN` و `APP_URL` باید دقیقاً با دامنهٔ مورد استفاده هماهنگ باشند. برای هر انتشار asset یک `APP_ASSET_VERSION` یکتا (مثلاً SHA کوتاه همان commit) قرار دهید تا cache immutable مرورگر پس از به‌روزرسانی invalidate شود. `SMS_DRIVER` عمداً روی `mock` است؛ فقط پس از وارد کردن کلیدها و شناسهٔ دستگاه واقعی، آن را به `textbee` تغییر دهید.

در `config.php` زبان و حالت اشکال‌زدایی را بررسی کنید (برای Production، `DEBUG_MODE = false` باشد). تنظیمات اتصال دیتابیس در `application/config/database.php` از متغیرهای امن محیطی Compose استفاده می‌کند و لازم نیست رمز دیتابیس در فایل PHP درج شود.

## Backup پیش از مهاجرت یا به‌روزرسانی

پیش از هر استقرار، یک dump سازگار با MySQL و کپی مستقل از `storage/uploads` و `config.php` بگیرید. بکاپ را بیرون از پوشهٔ پروژه و با دسترسی محدود نگه دارید. نمونهٔ زیر را با نام سرویس/کانتینر و مسیرهای واقعی همان سرور تطبیق دهید؛ **هیچ‌گاه** `docker compose down -v` را برای نگهداری داده اجرا نکنید:

```bash
umask 077
mkdir -p /root/ea-backups
STAMP="$(date +%Y%m%d-%H%M%S)"
# MYSQL_PWD را از secret manager یا محیط امن سرور بخوانید؛ رمز را در خط فرمان ثبت نکنید.
docker exec -e MYSQL_PWD="$MYSQL_ROOT_PASSWORD" <mysql-container> \
  mysqldump -uroot --single-transaction --routines --triggers --events easyappointments \
  > "/root/ea-backups/easyappointments-${STAMP}.sql"
tar -czf "/root/ea-backups/easyappointments-files-${STAMP}.tar.gz" \
  config.php .env storage/uploads
```

پس از ایجاد dump، اندازه و قابل‌خواندن بودن فایل را بررسی کنید و در صورت امکان بازیابی آن را روی یک دیتابیس جداگانه تمرین کنید.

> **مهاجرت از Compose فعلی:** تنظیمات فعلی از `./docker/mysql` استفاده می‌کند، اما فایل Production از volume مستقل `mysql_data` استفاده می‌کند. این volume به‌طور خودکار دیتابیس قبلی را وارد نمی‌کند. ابتدا بکاپ بگیرید، سپس dump را به MySQL Production وارد کنید. قبل از تأیید صحت اطلاعات، سرویس قدیمی یا volume قبلی را حذف نکنید.

## راه‌اندازی

```bash
docker compose -f docker-compose.prod.yml config --quiet
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml ps
```

برای لاگ‌ها:

```bash
docker compose -f docker-compose.prod.yml logs --tail=100 caddy nginx php-fpm mysql
```

پس از سالم شدن سرویس‌ها، `https://nobat.hoosna1402.ir` را باز کنید. Caddy برای صدور گواهی باید DNS عمومی صحیح و دسترسی ورودی روی 80 و 443 داشته باشد. خطاهای ACME را در لاگ Caddy بررسی کنید؛ آن‌ها را با خاموش‌کردن اعتبارسنجی TLS دور نزنید.

### وارد کردن dump به volume تازهٔ Production

این نمونه را فقط زمانی اجرا کنید که مطمئن شده‌اید dump به volume خالی و درست وارد می‌شود. مقادیر را از `.env` امن همان سرور بخوانید و از درج پسورد در history خودداری کنید:

```bash
docker compose -f docker-compose.prod.yml exec -T \
  -e MYSQL_PWD="$MYSQL_PASSWORD" mysql \
  mysql -u"$MYSQL_USER" "$MYSQL_DATABASE" < /root/ea-backups/easyappointments-YYYYMMDD-HHMMSS.sql
```

اگر دیتابیس اولیه از پیش ساخته شده، ابتدا فقط در یک volume آزمایشی بازیابی را بررسی کنید؛ وارد کردن dump روی نصب در حال استفاده می‌تواند داده‌ها را بازنویسی کند.

## استقرار نسخهٔ جدید و بازگشت

- برای انتشار کد جدید imageهای `php-fpm` و `nginx` را با همان tag/نسخه build کنید و `up -d` را اجرا کنید.
- volumeهای `mysql_data`, `app_storage`, `caddy_data` و `caddy_config` را نگه دارید. حذف آن‌ها داده‌ها یا گواهی‌های پایدار را از بین می‌برد.
- پیش از مهاجرت دیتابیس، سازگاری schema را بررسی کنید. بازگشت image به نسخهٔ پیشین جایگزین بازیابی دیتابیس نیست.
- خروجی `docker compose config` می‌تواند مقادیر interpolate شده را نشان دهد؛ آن را در issue، چت یا artifact عمومی قرار ندهید.

## نکات امنیتی

- `.env` و `config.php` شامل تنظیمات حساس‌اند و در git ignore شده‌اند؛ مجوز فایل‌ها را محدود نگه دارید.
- MySQL، Nginx و PHP-FPM پورت public ندارند؛ فقط Caddy پورت‌های وب را منتشر می‌کند.
- Caddy دادهٔ گواهی را در volume پایدار نگه می‌دارد. برای TLS از DNS صحیح و تمدید خودکار استفاده کنید.
- `storage/`, `application/`, `system/` و `vendor/` از Nginx قابل‌دسترسی مستقیم نیستند.
- پیامک واقعی تا زمان تنظیم TextBee عمداً خاموش می‌ماند.
- تست و build محلی: `npm run test:js` و `npm run build`. برای اجرای تست‌های PHP به PHP/Composer نیاز است.
