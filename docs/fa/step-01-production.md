# مرحله ۱ — زیرساخت تولید، امنیت و استقرار

> **محدودیت این مرحله:** SSH سرور قطع است و **هیچ دستوری روی سرور اجرا نشده است**. همهٔ فایل‌ها در
> همین ریپو ساخته شده‌اند و آنچه قابل تست بود در سندباکس (PHP-WASM + sqlite) تست شده است. هر جا اجرای
> سروری لازم است، با برچسب **«روی سرور»** و جایگزین مشخص شده است.

هدف مرحله: از «کانتینر دست‌ساز روی سرور» به استقرار **قابل بازتولید، امن و مستند** برسیم:
۳ سرویس، HTTPS روی `nobat.hoosna1402.ir`، بلاک‌کردن مسیرهای حساس، بکاپ/ریستور تست‌شده و لاگ‌گیری.

---

## ۱. فایل‌های ساخته‌شده در این مرحله

| فایل | نقش |
| --- | --- |
| `docker-compose.prod.yml` | استک تولید: `db` (MySQL 8، بدون پورت بیرونی) + `app` (php-fpm) + `web` (nginx، فقط `127.0.0.1`) |
| `docker-compose.caddy.yml` | لایهٔ اختیاری TLS: کانتینر Caddy روی ۸۰/۴۴۳ با گواهی خودکار Let's Encrypt |
| `docker/php-fpm/Dockerfile.prod` | ایمیج تولید (`php:8.2-fpm`، فقط اکستنشن‌های لازم، بدون xdebug) |
| `docker/php-fpm/prod-entrypoint.sh` | چک‌های پیش از اجرا (وجود `config.php`, `.env`, `vendor/`, مجوز `storage/`) |
| `docker/php-fpm/php-prod.ini` | تنظیمات PHP تولید (بدون `display_errors`، opcache، محدودیت‌ها) |
| `docker/php-fpm/php-fpm-prod.conf` | پول `www` تولید (`pm.max_children=8`، لاگ درخواست‌های کند) |
| `deploy/nginx/default.conf` | vhost امن nginx (بلاک `storage`, `application`, `docs`, `dev`, `tests`, `config.php`, …) |
| `deploy/Caddyfile` | vhost Caddy برای `nobat.hoosna1402.ir` (HTTPS + HSTS + reverse proxy) |
| `deploy/mysql/prod.cnf` | تنظیمات MySQL (utf8mb4، بافر کوچک برای سرور ۲GB، `skip_name_resolve`) |
| `deploy/systemd/ea-compose.service` | بالا آمدن استک پس از ریبوت سرور |
| `config-loader.php` | خواندن `.env` و ساختن کلاس `Config` (بدون هیچ تغییر در فایل‌های هسته) |
| `.env.example` | نمونهٔ کامل همهٔ کلیدها (بدون هیچ مقدار واقعی) |
| `application/controllers/Health.php` | اندپوینت `/index.php/health` (چک دیتابیس/جداول/migration/ذخیره‌سازی/SMS) |
| `scripts/deploy.sh` | استقرار idempotent: git pull، composer، gulp، build، up، مجوزها، smoke test |
| `scripts/backup.sh` | بکاپ دیتابیس (`--single-transaction`) + فایل‌ها + retention + کپی ریموت اختیاری |
| `scripts/restore.sh` | بازگردانی با تأیید، بررسی checksum، دامپ «pre-restore» و بررسی نهایی |
| `scripts/rollback.sh` | برگشت کد (کامیت قبلی) و/یا دیتابیس |
| `scripts/server-hardening.sh` | swap، ufw، بررسی پورت‌های داکر، SSH، fail2ban، MySQL، آپدیت خودکار |
| `scripts/demo-prod-stack.sh` | اجرای همان تنظیمات تولید **بدون داکر** روی سندباکس (تست جایگزین) |
| `tests/nginx/blocking.test.mjs` | ۱۱۳ assert روی قواعد امنیتی nginx/Caddy/compose و هم‌خوانی با سرور توسعه |
| `dev/sandbox/blocked-paths.mjs` | فهرست مشترک «مسیرهایی که هرگز سرو نمی‌شوند» |

**هیچ فایل هسته‌ای تغییر نکرده است** (تنها افزودنی‌ها: کنترلر `Health.php` و فایل‌های بالا).

---

## ۲. معماری استقرار

```
اینترنت ──HTTPS(443)──► Caddy (لایهٔ اختیاری، گواهی خودکار)
                             │  http://web:80
                             ▼
                        nginx  ──►  php-fpm (app:9000)  ──►  MySQL (db:3306)
                        (فقط فایل‌های عمومی)                (پورت منتشرشده ندارد)
   پورت‌های منتشرشده روی هاست: 80/443 (Caddy) و 127.0.0.1:8080 (nginx، فقط لوکال)
```

نکات کلیدی امنیتی (طبق شکاف‌های ۳۳/۳۴/۴۴ سند ممیزی مرحله ۰):

1. **MySQL هیچ پورت بیرونی ندارد**؛ فقط در شبکهٔ داخلی داکر دیده می‌شود.
2. **nginx فقط روی `127.0.0.1` منتشر می‌شود**؛ تنها راه رسیدن به آن Caddy است.
3. **`/storage`, `/application`, `/system`, `/vendor`, `/tests`, `/dev`, `/docs`, `/scripts`,
   `/docker`, `/deploy`, `/config.php`, `/config-loader.php`, `.env` و پسوندهای خطرناک
   (`sqlite`, `log`, `bak`, `gz`, `zip`, `md`, `json`, …) → `404`.** قبلاً `/storage/sessions`
   (ربایش نشست) و `/storage/backups` (دیتابیس کامل مشتریان) قابل دانلود بود.
4. **فقط `/index.php` اجرا می‌شود**؛ هر فایل `.php` دیگری `404` می‌گیرد.
5. `/health` فقط از شبکهٔ داخلی/لوکال قابل دسترسی است و برای کاربران اینترنت فقط `{"status":"ok"}`
   برمی‌گرداند (بدون افشای جزئیات).
6. همهٔ رمزها از `.env` می‌آیند؛ `.env` در `.gitignore` است و `.env.example` فقط کلید خالی دارد.

---

## ۳. نصب روی سرور (پس از برگشت SSH — هنوز اجرا نشده)

### ۳-۱. پیش‌نیازها

```bash
# ۱) مطمئن شوید پورت ۸۰/۴۴۳ دست چه کسی است (نورستان؟ nginx syستمی؟)
sudo ss -tlnp | grep -E ':(80|443)\s'

# ۲) نسخهٔ داکر (سرور طبق سند: Docker 29.1.3 + docker-compose 1.29.2)
docker version && (docker compose version || docker-compose --version)

# ۳) فایل‌سیستم و رم
free -h && df -h / && swapon --show
```

### ۳-۲. کد و تنظیمات

```bash
cd /var/www/easyappointments          # مسیر فعلی برنامه روی سرور
git fetch origin
git checkout main && git merge --ff-only origin/main     # مرحله ۱
cp .env.example .env && chmod 600 .env && nano .env
#   APP_ENV=production، APP_URL=https://nobat.hoosna1402.ir
#   ENCRYPTION_KEY=$(openssl rand -hex 32)
#   DB_PASSWORD=…، MYSQL_ROOT_PASSWORD=…
#   SMS_DRIVER=textbee + TEXTBEE_API_KEY / TEXTBEE_DEVICE_ID (اگر پیامک واقعی می‌خواهید)

# config.php فعلی سرور را نگه دارید و به «تک‌خطی» تبدیل کنید:
sudo cp config.php /root/config.php.backup-$(date +%F)
sudo tee config.php >/dev/null <<'PHP'
<?php require_once __DIR__ . '/config-loader.php';
PHP
```

> **هشدار:** اگر سرور شما `config.php` را با مقادیر ثابت دارد و ترجیح می‌دهید دست‌نخورده بماند،
> لازم نیست تغییری بدهید: `config-loader.php` فقط زمانی کلاس `Config` را می‌سازد که تعریف نشده باشد.
> یعنی می‌توانید `config.php` قدیمی را نگه دارید و فقط دیتابیس/رمز را داخل آن به‌روز کنید
> (در آن حالت کلیدهای `.env` فقط برای TextBee و کلید cron لازم‌اند).

### ۳-۳. استقرار

```bash
bash scripts/deploy.sh --dry-run     # اول ببینید چه کاری انجام می‌شود (بدون تغییر)
bash scripts/deploy.sh               # استقرار کامل: composer, gulp, build, up, smoke test
bash scripts/deploy.sh --with-migrate   # با بکاپ خودکار و سپس migrate
```

### ۳-۴. HTTPS

**سناریو A — پورت‌های ۸۰/۴۴۳ آزاد هستند:**

```bash
echo 'ACME_EMAIL=you@example.com' >> .env
bash scripts/deploy.sh --skip-pull --with-caddy
# یا:
docker compose -f docker-compose.prod.yml -f docker-compose.caddy.yml up -d
```

**سناریو B — روی سرور همین حالا nginx/apache برای سایت اصلی کار می‌کند (احتمال زیاد):**
پورت ۸۰/۴۴۳ را به Caddy ندهید (سایت اصلی از دسترس خارج می‌شود). یکی از دو راه:

* **B1:** vhost جدید به وب‌سرور فعلی اضافه کنید و گواهی را با certbot بگیرید:

```nginx
# /etc/nginx/sites-available/nobat.conf  (روی سرور)
server {
    listen 80;
    server_name nobat.hoosna1402.ir;

    location / {
        proxy_pass http://127.0.0.1:8080;      # کانتینر web
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/nobat.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d nobat.hoosna1402.ir --redirect
```

* **B2:** Caddy را به‌عنوان وب‌سرور اصلی بگیرید و سایت اصلی (نورستان) را به‌شکل یک vhost پروکسی
  در همان Caddyfile تعریف کنید. **قبل از این کار با کاربر تأیید بگیرید؛ خطر اختلال در سایت اصلی.**

### ۳-۵. cron

```bash
sudo crontab -e
```

```cron
* * * * * cd /var/www/easyappointments && docker compose -f docker-compose.prod.yml exec -T app php index.php sms_maintenance process_queue >/dev/null 2>&1
* * * * * cd /var/www/easyappointments && docker compose -f docker-compose.prod.yml exec -T app php index.php booking_maintenance release_expired_holds >/dev/null 2>&1
17 4 * * * cd /var/www/easyappointments && docker compose -f docker-compose.prod.yml exec -T app php index.php sms_maintenance cleanup_otp_codes >/dev/null 2>&1
30 3 * * * cd /var/www/easyappointments && bash scripts/backup.sh >> storage/logs/backup.log 2>&1
```

### ۳-۶. بکاپ و بازگردانی

```bash
bash scripts/backup.sh                   # دیتابیس + فایل‌ها + پاک‌سازی قدیمی‌ها
bash scripts/backup.sh --database-only    # بکاپ سریع دیتابیس
bash scripts/restore.sh --list            # فهرست بکاپ‌ها
bash scripts/restore.sh --database /var/backups/easyappointments/ea-db-20260930-033000.sql --yes
bash scripts/rollback.sh                  # وضعیت + راهنمای برگشت
bash scripts/rollback.sh --code           # برگشت به کامیت قبلی + ری‌استارت
```

### ۳-۷. سخت‌سازی سرور

```bash
sudo bash scripts/server-hardening.sh --check      # فقط گزارش (بی‌خطر)
sudo bash scripts/server-hardening.sh --swap       # ساخت ۲GB swap
sudo bash scripts/server-hardening.sh --firewall   # ufw: اول SSH بعد ۸۰/۴۴۳
sudo bash scripts/server-hardening.sh --docker     # هشدار برای پورت‌های ۰.۰.۰.۰
sudo bash scripts/server-hardening.sh --fail2ban
# فقط اگر کلید SSH دارید:
sudo bash scripts/server-hardening.sh --ssh
```

> **هشدار امنیتی:** هرگز قبل از تأیید باز بودن پورت SSH و داشتن یک نشست دوم،
> `ufw enable` یا غیرفعال‌کردن ورود با رمز را انجام ندهید. اسکریپت همین ترتیب را رعایت می‌کند
> اما مسئولیت نهایی با اپراتور است.

---

## ۴. تست و شواهد (همه در سندباکس اجرا شده است)

### ۴-۱. تست‌های خودکار

```
node dev/sandbox/run.mjs --phpunit --configuration phpunit.xml
→ OK (93 tests, 7580 assertions)

npm run test:js        → 23 passed, 0 failed   (تقویم جلالی)
npm run test:config    → 113 passed, 0 failed  (nginx/Caddy/compose/.env + هم‌خوانی با سرور توسعه)
```

`tests/nginx/blocking.test.mjs` قواعد `deploy/nginx/default.conf` را مثل خود nginx ارزیابی می‌کند
(اولین location ای که مطابقت کند برنده است) و مطمئن می‌شود که:
`/storage/...`، `/config.php`، `/application/...`، `/tests/...`، `/dev/...`، `/.env` و هر `.php`
غیر از `index.php` بلاک می‌شوند و `/index.php` و `/assets/...` باز می‌مانند.

### ۴-۲. اجرای همان تنظیمات تولید بدون داکر (جایگزین تست سروری)

```bash
bash scripts/demo-prod-stack.sh --check-only
```

خروجی واقعی اجرا در سندباکس:

```
ok  The application is configured from the environment (APP_ENV=development).
ok  health endpoint answers (HTTP 200)
ok  storage directory is blocked (HTTP 404)
ok  application directory is blocked (HTTP 404)
ok  docs directory is blocked (HTTP 404)
ok  dev directory is blocked (HTTP 404)
ok  tests directory is blocked (HTTP 404)
ok  config.php is blocked (HTTP 404)
ok  the home page still works (HTTP 200)
ok  The production configuration passed all checks.
    {"status":"ok","failed":[],"checks":{"database":{"status":"ok"},"tables":{"status":"ok"},
     "migrations":{"status":"ok","version":75},"storage":{"status":"ok"},
     "sms":{"status":"ok","driver":"mock"}},"time":"2026-09-30T20:35:58+00:00"}
```

> تنها تفاوت با تولید در این اجرا `APP_ENV=development` است، چون سندباکس sqlite دارد و برنامه فایل
> `application/config/development/database.php` را فقط در محیط development استفاده می‌کند. روی سرور
> `APP_ENV=production` است و دیتابیس MySQL همان کانتینر است.

### ۴-۳. تست‌هایی که **فقط روی سرور** معنا دارند (انجام‌نشده)

| # | تست | دستور |
| --- | --- | --- |
| ۱ | بالا آمدن استک از صفر | `bash scripts/deploy.sh` |
| ۲ | سلامت همهٔ سرویس‌ها | `docker compose -f docker-compose.prod.yml ps` |
| ۳ | اندپوینت سلامت از داخل | `curl -fsS http://127.0.0.1:8080/index.php/health` |
| ۴ | بستهٔ بودن پورت‌های دیتابیس | `nmap -Pn -p 3306,9000 91.247.171.117` → باید `closed` باشد |
| ۵ | بلاک‌بودن مسیرهای حساس از بیرون | `curl -sI https://nobat.hoosna1402.ir/storage/backups/` → `404` |
| ۶ | گواهی TLS | `curl -sSI https://nobat.hoosna1402.ir | head -1` و `openssl s_client -connect nobat.hoosna1402.ir:443` |
| ۷ | بکاپ و بازگردانی واقعی | `bash scripts/backup.sh` سپس `restore.sh --list` و بازیابی روی یک دیتابیس تست |
| ۸ | اجرای migrationها | `bash scripts/deploy.sh --with-migrate` (۰۷۲ تا ۰۷۵) |
| ۹ | cron و صف پیامک | `php index.php sms_maintenance process_queue` |

---

## ۵. معیار پذیرش مرحله ۱

- [x] `docker-compose.prod.yml` با ۳ سرویس، MySQL بدون پورت بیرونی و healthcheck.
- [x] ایمیج تولید Dockerfile در گیت (به‌جای Dockerfile فقط-سرور) + entrypoint با چک‌های پیش از اجرا.
- [x] `.env.example` + `config-loader.php`؛ هیچ رمزی در گیت نیست و `.env` در `.gitignore` است.
- [x] `scripts/deploy.sh`، `backup.sh`، `restore.sh`، `rollback.sh`، `server-hardening.sh` (همه
      با `bash -n` و اجرای واقعی بخش‌های سندباکسی تست شده‌اند).
- [x] Caddy برای HTTPS روی `nobat.hoosna1402.ir` + مسیر جایگزین برای وقتی پورت ۸۰/۴۴۳ در اختیار
      سایت اصلی است.
- [x] nginx با بلاک `/storage /application /docs /dev /tests /config.php` (تست خودکار دارد).
- [x] اندپوینت سلامت + smoke test در `deploy.sh`.
- [x] مستند فارسی + جدول تست‌های باقی‌مانده.
- [ ] اجرای واقعی روی سرور پس از برگشت SSH (بخش ۴-۳).

## ۶. ریسک‌ها و هشدارها

1. **پورت ۸۰/۴۴۳:** اگر Caddy را بدون بررسی بالا بیاورید، سایت اصلی (نورستان) از دسترس خارج می‌شود.
   ابتدا `sudo ss -tlnp | grep -E ':(80|443)\s'` و سناریو B را انتخاب کنید. (ریسک بالا)
2. **دو MySQL موازی:** طبق ممیزی، هم MySQL سیستمی و هم MySQL داکری فعال است. کانتینر `db` این
   استک منبع حقیقت است؛ برای جلوگیری از سردرگمی در بکاپ، MySQL سیستمی را فقط‌خواندنی/خاموش کنید.
3. **حافظهٔ سرور ۲GB:** `pm.max_children=8` و `innodb_buffer_pool_size=256M` محافظه‌کارانه انتخاب
   شده‌اند. پس از استقرار با `docker stats` و `free -h` بسنجید و در صورت نیاز همراه با swap تنظیم کنید.
4. **مهاجرت‌ها:** `deploy.sh` هیچ‌وقت خودکار migrate نمی‌کند جز با `--with-migrate` که اول بکاپ
   می‌گیرد و تأیید می‌خواهد. (قاعدهٔ ۵ کاربر)
5. **`.env` روی سرور:** باید مالک اپراتور و mode `600` باشد؛ `config-loader.php` در صورت
   خوانا بودن برای دیگران در error_log هشدار می‌دهد.
6. **حذف/تغییر کانتینر فعلی:** استک فعلی سرور (nginx/mysql دست‌ساز) با این فایل‌ها جایگزین می‌شود.
   قبل از `up -d` از دیتابیس فعلی بکاپ بگیرید: `bash scripts/backup.sh` (فقط پس از بالا آمدن `db`).
   برای دیتابیس موجودِ سرور، دامپ دستی زیر را نگه دارید:
   `mysqldump --single-transaction --routines --triggers -u root -p easyappointments > db-before-step1.sql`
