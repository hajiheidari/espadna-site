# هاست ایران (espadna.ir): نسخه‌ی پشتیبان برای اینترنت ملی

`sync.php` هر چند دقیقه سایت‌های espadna.com را روی هاست ایران کپی می‌کند:
فقط فایل‌هایی که عوض شده‌اند، با بررسی سالم بودن هر فایل. اگر Cloudflare در
دسترس نباشد (اینترنت ملی) به هیچ چیز دست نمی‌زند و آخرین نسخه سرو می‌شود.
لینک‌های داخل صفحه‌ها از `espadna.com` به `espadna.ir` تبدیل می‌شوند. SSH
لازم نیست.

| espadna.com | espadna.ir |
|---|---|
| `espadna.com` (سایت استودیو و صفحه‌های معرفی) | `espadna.ir` |
| `pantomime.espadna.com` (بازی) | `pantomime.espadna.ir` |
| `api.espadna.com` (تنظیمات اپ، فهرست کلمه، حریم خصوصی) | `api.espadna.ir` |

## راه‌اندازی (یک بار، از پنل هاست)

1. **زیر‌دامنه‌ها:** `pantomime.espadna.ir` و `api.espadna.ir` را بسازید. مسیر
   (Document Root) هرکدام را **بیرون از** `public_html` بگذارید، مثلاً
   `/home/USERNAME/pantomime.espadna.ir`.
2. **SSL** رایگان (Let's Encrypt / AutoSSL) را برای هر سه دامنه فعال کنید.
3. پوشه‌ی `/home/USERNAME/espadna-sync` را بسازید (بیرون از `public_html`) و
   `sync.php` را در آن آپلود کنید.
4. `config.sample.php` را با اسم `config.php` همان‌جا بگذارید و
   `USERNAME` و مسیرها را مطابق پنل درست کنید.
5. **Cron Job** هر یک دقیقه:
   `php /home/USERNAME/espadna-sync/sync.php`
   (در بعضی هاست‌ها: `/usr/local/bin/php ...`)
6. چند دقیقه بعد `https://espadna.ir/mirror-status.json` را باز کنید:
   `"result"` باید `updated ...` یا `up to date` باشد. گزارش کامل در
   `espadna-sync/sync.log` است.

## اپ جدید

یک مورد به `sites` در `config.php` اضافه کنید (و زیر‌دامنه‌اش را بسازید). سایت
اپ باید در ریشه‌اش `files.json` منتشر کند (پانتومیم: `tool/web_finish.mjs`،
espadna.com: `tools/manifest.py` در workflow).
