# Rules for working on espadna.com

- `git pull` first. Several sessions (one per app) edit this repo.
- Only touch your app's folder `public/<app>-app/`, its entry in
  `products.json`, and its lines in `public/sitemap.xml` / `public/_redirects`.
  Change shared files (studio home text, `tools/`, `public/brand/`) only when
  asked to.
- After editing `products.json`, run `python3 tools/site_home.py` and commit
  the generated `public/index.html`, `public/fa/`, `public/ar/`.
- Do not deploy by hand (`wrangler deploy`). Pushing to `main` deploys via
  GitHub Actions.
- English at the root of every section, Persian in `fa/`, Arabic in `ar/`.
- Tiaro's landing pages are NOT here (served by its own Worker route, see README); keep only its card and sitemap lines.
