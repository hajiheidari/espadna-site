# espadna.com

Studio home page and the landing page of every Espadna app.

| Path | What |
|---|---|
| `/` · `/fa/` · `/ar/` | studio home in English, Persian, Arabic (generated) |
| `/<app>-app/` · `/<app>-app/fa/` · `/<app>-app/ar/` | landing page of one app |
| `/<app>-app/privacy` | privacy policy (often a redirect, see `public/_redirects`) |
| `<app>.espadna.com` | the app's web version, **not** in this repo (each app deploys its own) |

### Apps that serve their own landing pages

Some apps publish `/<app>-app/*` from their own repo through a Worker route on
the espadna.com zone (a route wins over this site). Only their card in
`products.json` and their sitemap lines live here.

| App | Landing pages served by | Repo |
|---|---|---|
| Tiaro | Worker `crowns-app` (route `espadna.com/tiaro-app*`) | hajiheidari/crowns-app (`web/tiaro-app/`) |
| Pantomime | this repo (`public/pantomime-app/`) | hajiheidari/pantomime (game) |

English is always the root of a section; Persian and Arabic live in `fa/` and `ar/`.

## Add or change an app

1. Put its landing pages in `public/<app>-app/` (`index.html` English, `fa/`, `ar/`)
   with its own assets (icon, og image, screenshots). Use absolute paths
   (`/<app>-app/icon.png`).
2. Add it to `products.json` (name, description, icon, page and web links in
   `en`, `fa`, `ar`).
3. `python3 tools/site_home.py` regenerates the three home pages.
4. Add its URLs to `public/sitemap.xml` (and redirects to `public/_redirects`).
5. Commit and push to `main`. **GitHub Actions deploys it; never run
   `wrangler deploy` by hand**, so two people/sessions can't overwrite each
   other. Always `git pull` before you start.

## Look

Formal studio style: navy `#0B1F3A`, blue `#2563EB`, light background; logo in
`public/brand/` (`logo.svg` is the source). App landing pages may use the app's
own style.
