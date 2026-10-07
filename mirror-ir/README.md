# هاست ایران (espadna.ir): نسخه‌ی پشتیبان برای اینترنت ملی

`sync.php` هر چند دقیقه سایت‌های espadna.com را روی هاست ایران کپی می‌کند:
فقط فایل‌هایی که عوض شده‌اند، با بررسی سالم بودن هر فایل. اگر Cloudflare در
دسترس نباشد (اینترنت ملی) به هیچ چیز دست نمی‌زند و آخرین نسخه سرو می‌شود.
لینک‌های داخل صفحه‌ها از `espadna.com` به `espadna.ir` تبدیل می‌شوند (به‌جز
لینک canonical، تا گوگل نسخه‌ی ir را «تکراری» حساب نکند). SSH و دیتابیس لازم نیست.

| espadna.com | espadna.ir | پوشه روی هاست (DirectAdmin) |
|---|---|---|
| `espadna.com` (سایت استودیو و صفحه‌های معرفی) | `espadna.ir` | `public_html` |
| `api.espadna.com` (تنظیمات اپ، فهرست کلمه، حریم خصوصی) | `espadna.ir/api` | `public_html/api` |
| `pantomime.espadna.com` (بازی) | `pantomime.espadna.ir` | `public_html/pantomime` |
| `tiaro.espadna.com` | `tiaro.espadna.ir` | `public_html/tiaro` |
| `espadna.com/tiaro-app` | `espadna.ir/tiaro-app` | `public_html/tiaro-app` |

(`public_html` یعنی `/home/USERNAME/domains/espadna.ir/public_html`.)

## راه‌اندازی (یک بار، از پنل DirectAdmin)

1. **زیردامنه‌ها** (`pantomime`، `tiaro`) را بسازید. پوشه‌ی `api` را خود
   اسکریپت می‌سازد.
2. **SSL:** در SSL Certificates گزینه‌ی Let's Encrypt را برای `espadna.ir`،
   `www` و زیردامنه‌ها بگیرید. در Domain Setup، `private_html` باید «لینک به
   public_html» باشد (پیش‌فرض همین است).
3. **فایل‌ها:** در File Manager، در پوشه‌ی خانه (`/home/USERNAME`، بیرون از
   `domains`) پوشه‌ی `espadna-sync` را بسازید و `sync.php` و `config.php` را در آن
   بگذارید. `config.php` همان `config.sample.php` است با `USERNAME` درست.
4. **Cron Job** (Advanced Features ← Cronjobs)، همه‌ی ستون‌ها `*`، فرمان:
   `/usr/local/bin/php /home/USERNAME/espadna-sync/sync.php >/dev/null 2>&1`
5. چند دقیقه بعد `https://espadna.ir/mirror-status.json` را باز کنید:
   `"result"` باید `updated ...` یا `up to date` باشد. گزارش کامل در
   `espadna-sync/sync.log` است. `unreachable` یعنی PHP هاست به سایت‌های خارجی
   وصل نمی‌شود (از پشتیبانی بخواهید باز کنند).

**منبع فایل‌ها گیت‌هاب است، نه Cloudflare:** از هاست ایرانی، دانلود از Cloudflare بعد
از حدود ۶۳ کیلوبایت متوقف می‌شود (تست مهر ۱۴۰۵ روی irwebspace)، ولی گیت‌هاب کار
می‌کند. workflow `mirror-pack` هر ۱۰ دقیقه (و بعد از هر انتشار سایت) نسخه‌ی زنده‌ی
سایت‌ها را در شاخه‌ی `mirror` همین مخزن می‌گذارد (`tools/mirror_pack.py`، همیشه
یک commit، پس مخزن بزرگ نمی‌شود) و `sync.php` از `raw.githubusercontent.com`
می‌خواند. سالم بودن هر فایل با sha256 فهرست `files.json` چک می‌شود.

## اپ جدید

یک مورد به `sites` در `config.php` و به `FILE_SITES` در `tools/mirror_pack.py`
اضافه کنید (و زیردامنه‌اش را بسازید). سایت
اپ باید در ریشه‌اش `files.json` منتشر کند: فهرست فایل‌ها با sha256 و متن
`_redirects` در فیلد `redirects` (پانتومیم: `tool/web_finish.mjs`، espadna.com:
`tools/manifest.py` در workflow). تا آن موقع وضعیتش `source has no files.json yet` است.
