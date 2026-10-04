#!/usr/bin/env python3
"""Writes the espadna.com home pages (en at /, fa at /fa/, ar at /ar/) from
products.json. Run after adding or changing a product:

    python3 tools/site_home.py
"""
import html
import json
import pathlib

ROOT = pathlib.Path(__file__).resolve().parent.parent
SITE = ROOT / 'public'
PRODUCTS = json.loads((ROOT / 'products.json').read_text(encoding='utf-8'))['products']

LANGS = {
    'en': dict(path='', dir='ltr', name='Espadna', locale='en_US', label='English',
               title='Espadna | Simple, useful apps & games',
               desc='Espadna builds simple, useful and reliable apps and games for Android, iPhone and the web.',
               hero='Simple, useful and reliable apps & games',
               lead='We build products that just work: fast, lightweight, multilingual, and usable even offline.',
               products='Products', details='Details & download', web='Web version',
               about='About us',
               about_text='Espadna is a small software studio focused on quality, user privacy, and staying reachable on unreliable networks.',
               points=['Privacy first: no sign-up, no personal data collected', 'Works offline and on limited networks', 'English, Persian and Arabic'],
               menu=['Products', 'About']),
    'fa': dict(path='fa/', dir='rtl', name='اسپادنا', locale='fa_IR', label='فارسی',
               title='اسپادنا | اپ‌ها و بازی‌های ساده و کاربردی',
               desc='اسپادنا اپ‌ها و بازی‌های ساده، کاربردی و قابل اعتماد می‌سازد؛ برای اندروید، آیفون و وب.',
               hero='اپ‌ها و بازی‌های ساده، کاربردی و قابل اعتماد',
               lead='ما محصولاتی می‌سازیم که بدون دردسر کار می‌کنند: سریع، سبک، دوزبانه و حتی بدون اینترنت.',
               products='محصولات', details='معرفی و دانلود', web='نسخه‌ی وب',
               about='درباره‌ی ما',
               about_text='اسپادنا یک استودیوی کوچک نرم‌افزار است. روی کیفیت، حریم خصوصی کاربر و دسترسی‌پذیری در شرایط اینترنت ایران تمرکز داریم.',
               points=['حریم خصوصی: بدون ثبت‌نام و بدون جمع‌آوری اطلاعات شخصی', 'کار بدون اینترنت و در شرایط اینترنت محدود', 'فارسی، انگلیسی و عربی'],
               menu=['محصولات', 'درباره‌ی ما']),
    'ar': dict(path='ar/', dir='rtl', name='إسبادنا', locale='ar_AR', label='العربية',
               title='إسبادنا | تطبيقات وألعاب بسيطة ومفيدة',
               desc='تصمم إسبادنا تطبيقات وألعابًا بسيطة ومفيدة وموثوقة لأندرويد وآيفون والويب.',
               hero='تطبيقات وألعاب بسيطة ومفيدة وموثوقة',
               lead='نصنع منتجات تعمل دون تعقيد: سريعة وخفيفة ومتعددة اللغات، وتعمل حتى دون إنترنت.',
               products='منتجاتنا', details='التفاصيل والتحميل', web='نسخة الويب',
               about='من نحن',
               about_text='إسبادنا استوديو برمجيات صغير يركز على الجودة وخصوصية المستخدم وإتاحة الخدمة حتى مع ضعف الاتصال.',
               points=['الخصوصية أولًا: دون تسجيل ودون جمع بيانات شخصية', 'تعمل دون إنترنت وعلى الشبكات المحدودة', 'العربية والإنجليزية والفارسية'],
               menu=['المنتجات', 'من نحن']),
}

CSS = """
    @font-face { font-family: Vazirmatn; font-weight: 400 900; font-display: swap; src: url(/Vazirmatn-Bold.ttf) format("truetype"); }
    :root {
      --navy: #0B1F3A; --blue: #2563EB; --blue-soft: #EAF1FE; --ink: #0F172A;
      --muted: #5B6B82; --line: #E3E8EF; --bg: #F6F8FB; --card: #FFFFFF;
    }
    * { box-sizing: border-box; }
    html { -webkit-text-size-adjust: 100%; background: var(--bg); }
    body { margin: 0; color: var(--ink); background: var(--bg); line-height: 1.75;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    body[dir="rtl"] { font-family: Vazirmatn, Tahoma, system-ui, sans-serif; }
    a { color: var(--blue); }
    .wrap { max-width: 1040px; margin: 0 auto; padding: 0 20px; }
    header { background: var(--navy); color: #fff; }
    .bar { display: flex; align-items: center; justify-content: space-between; gap: 12px 16px; padding-block: 14px; flex-wrap: wrap; }
    .brand { display: flex; align-items: center; gap: 10px; color: #fff; text-decoration: none; font-weight: 800; font-size: 20px; }
    .brand img { width: 36px; height: 36px; }
    nav { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; font-size: 15px; }
    nav a { color: #C9D6E8; text-decoration: none; }
    nav a:hover { color: #fff; }
    .langs { display: flex; gap: 4px; border: 1px solid rgba(255,255,255,.18); border-radius: 8px; padding: 2px; }
    .langs a { padding: 2px 10px; border-radius: 6px; }
    .langs a[aria-current] { background: rgba(255,255,255,.14); color: #fff; }
    .hero { padding-block: 64px 72px; }
    .hero h1 { font-size: clamp(30px, 5.2vw, 48px); line-height: 1.3; margin: 0 0 16px; font-weight: 800; max-width: 760px; }
    .hero p { color: #C9D6E8; font-size: 18px; max-width: 640px; margin: 0; }
    section { padding: 56px 0 8px; }
    h2 { font-size: 26px; margin: 0 0 20px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; }
    .product { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 20px; display: flex; gap: 16px; }
    .product img { width: 64px; height: 64px; border-radius: 14px; flex: none; }
    .kind { display: inline-block; font-size: 12px; font-weight: 700; color: var(--blue); background: var(--blue-soft); border-radius: 999px; padding: 0 10px; }
    .product h3 { margin: 6px 0 4px; font-size: 19px; }
    .product p { margin: 0 0 14px; color: var(--muted); font-size: 15px; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn { display: inline-block; text-decoration: none; font-weight: 700; font-size: 14px; border-radius: 8px; padding: 7px 14px; border: 1px solid var(--line); color: var(--ink); background: #fff; }
    .btn.primary { background: var(--blue); border-color: var(--blue); color: #fff; }
    .about { display: grid; grid-template-columns: 1.2fr 1fr; gap: 28px; align-items: start; }
    .about p { color: var(--muted); margin: 0; }
    .about ul { margin: 0; padding: 0; list-style: none; display: grid; gap: 10px; }
    .about li { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-weight: 600; font-size: 15px; }
    footer { margin-top: 64px; border-top: 1px solid var(--line); color: var(--muted); font-size: 14px; }
    footer .wrap { padding: 22px 20px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    @media (max-width: 720px) { .about { grid-template-columns: 1fr; } .hero { padding-block: 44px 52px; } }
"""


def page(code):
    L = LANGS[code]
    url = f'https://espadna.com/{L["path"]}'
    og = {'fa': 'og.png', 'en': 'og_en.png', 'ar': 'og_ar.png'}[code]
    alternates = '\n'.join(
        f'  <link rel="alternate" hreflang="{c}" href="https://espadna.com/{v["path"]}">' for c, v in LANGS.items()
    ) + '\n  <link rel="alternate" hreflang="x-default" href="https://espadna.com/">'
    current = ' aria-current="page"'
    langs = ''.join(
        f'<a href="/{v["path"]}" hreflang="{c}" lang="{c}"{current if c == code else ""}>{v["label"]}</a>'
        for c, v in LANGS.items()
    )
    e = html.escape
    cards = '\n'.join(f'''      <article class="product">
        <img src="{p['icon']}" alt="" width="64" height="64" loading="lazy">
        <div>
          <span class="kind">{e(p['kind'][code])}</span>
          <h3>{e(p['name'][code])}</h3>
          <p>{e(p['desc'][code])}</p>
          <div class="actions">
            <a class="btn primary" href="{p['page'][code]}">{e(L['details'])}</a>
            <a class="btn" href="{p['web'][code]}">{e(L['web'])}</a>
          </div>
        </div>
      </article>''' for p in PRODUCTS)
    points = ''.join(f'<li>{e(x)}</li>' for x in L['points'])
    return f'''<!DOCTYPE html>
<!-- Generated by tools/site_home.py from products.json — edit those, not this file. -->
<html lang="{code}" dir="{L['dir']}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>{e(L['title'])}</title>
  <meta name="description" content="{e(L['desc'])}">
  <meta name="theme-color" content="#0B1F3A">
  <link rel="canonical" href="{url}">
{alternates}
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="/brand/logo-64.png" type="image/png" sizes="64x64">
  <link rel="apple-touch-icon" href="/brand/logo-180.png">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="{e(L['name'])}">
  <meta property="og:locale" content="{L['locale']}">
  <meta property="og:title" content="{e(L['title'])}">
  <meta property="og:description" content="{e(L['desc'])}">
  <meta property="og:url" content="{url}">
  <meta property="og:image" content="https://espadna.com/brand/{og}">
  <meta name="twitter:card" content="summary_large_image">
  <script type="application/ld+json">{json.dumps({"@context": "https://schema.org", "@type": "Organization", "name": "Espadna", "alternateName": ["اسپادنا", "إسبادنا"], "url": "https://espadna.com/", "logo": "https://espadna.com/brand/logo-512.png"}, ensure_ascii=False)}</script>
  <style>{CSS}  </style>
</head>
<body dir="{L['dir']}">
  <header>
    <div class="wrap bar">
      <a class="brand" href="/{L['path']}"><img src="/brand/logo.svg" alt="" width="36" height="36">{e(L['name'])}</a>
      <nav>
        <a href="#products">{e(L['menu'][0])}</a>
        <a href="#about">{e(L['menu'][1])}</a>
        <span class="langs">{langs}</span>
      </nav>
    </div>
    <div class="wrap hero">
      <h1>{e(L['hero'])}</h1>
      <p>{e(L['lead'])}</p>
    </div>
  </header>
  <main class="wrap">
    <section id="products">
      <h2>{e(L['products'])}</h2>
      <div class="grid">
{cards}
      </div>
    </section>
    <section id="about" class="about">
      <div>
        <h2>{e(L['about'])}</h2>
        <p>{e(L['about_text'])}</p>
      </div>
      <ul>{points}</ul>
    </section>
  </main>
  <footer>
    <div class="wrap"><span>© {e(L['name'])}{'' if L['name'] == 'Espadna' else ' · Espadna'}</span><span>espadna.com</span></div>
  </footer>
</body>
</html>
'''


for code, L in LANGS.items():
    out = SITE / L['path'] / 'index.html'
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(page(code), encoding='utf-8')
    print('wrote', out.relative_to(ROOT))
