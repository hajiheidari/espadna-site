// Offline support for the web version. tools/web_build.mjs replaces
// fd079ae21ce6 (changes with every release) and ["./",".last_build_id","assets/AssetManifest.bin","assets/AssetManifest.bin.json","assets/FontManifest.json","assets/assets/cards/back.webp","assets/assets/cards/backs/emerald.webp","assets/assets/cards/backs/paisley.webp","assets/assets/cards/backs/ruby.webp","assets/assets/cards/backs/starry.webp","assets/assets/cards/backs/sun.webp","assets/assets/cards/decks/engraving/executioner.webp","assets/assets/cards/decks/engraving/joker.webp","assets/assets/cards/decks/engraving/king.webp","assets/assets/cards/decks/engraving/minister.webp","assets/assets/cards/decks/engraving/sipahi.webp","assets/assets/cards/decks/engraving/thief.webp","assets/assets/cards/decks/engraving/thief2.webp","assets/assets/cards/decks/qajar/executioner.webp","assets/assets/cards/decks/qajar/joker.webp","assets/assets/cards/decks/qajar/king.webp","assets/assets/cards/decks/qajar/minister.webp","assets/assets/cards/decks/qajar/sipahi.webp","assets/assets/cards/decks/qajar/thief.webp","assets/assets/cards/decks/qajar/thief2.webp","assets/assets/cards/decks/shadow/executioner.webp","assets/assets/cards/decks/shadow/joker.webp","assets/assets/cards/decks/shadow/king.webp","assets/assets/cards/decks/shadow/minister.webp","assets/assets/cards/decks/shadow/sipahi.webp","assets/assets/cards/decks/shadow/thief.webp","assets/assets/cards/decks/shadow/thief2.webp","assets/assets/cards/executioner.webp","assets/assets/cards/joker.webp","assets/assets/cards/khatam/executioner.webp","assets/assets/cards/khatam/joker.webp","assets/assets/cards/khatam/king.webp","assets/assets/cards/khatam/minister.webp","assets/assets/cards/khatam/sipahi.webp","assets/assets/cards/khatam/thief.webp","assets/assets/cards/khatam/thief2.webp","assets/assets/cards/king.webp","assets/assets/cards/miniature/executioner.webp","assets/assets/cards/miniature/joker.webp","assets/assets/cards/miniature/king.webp","assets/assets/cards/miniature/minister.webp","assets/assets/cards/miniature/sipahi.webp","assets/assets/cards/miniature/thief.webp","assets/assets/cards/miniature/thief2.webp","assets/assets/cards/minister.webp","assets/assets/cards/silver/executioner.webp","assets/assets/cards/silver/joker.webp","assets/assets/cards/silver/king.webp","assets/assets/cards/silver/minister.webp","assets/assets/cards/silver/sipahi.webp","assets/assets/cards/silver/thief.webp","assets/assets/cards/silver/thief2.webp","assets/assets/cards/sipahi.webp","assets/assets/cards/thief.webp","assets/assets/cards/thief2.webp","assets/assets/cards/tile/executioner.webp","assets/assets/cards/tile/joker.webp","assets/assets/cards/tile/king.webp","assets/assets/cards/tile/minister.webp","assets/assets/cards/tile/sipahi.webp","assets/assets/cards/tile/thief.webp","assets/assets/cards/tile/thief2.webp","assets/assets/fonts/CormorantGaramond-Bold.ttf","assets/assets/fonts/NotoSansDevanagari-Bold.ttf","assets/assets/fonts/NotoSansDevanagari-Regular.ttf","assets/assets/fonts/Vazirmatn-Black.ttf","assets/assets/fonts/Vazirmatn-Bold.ttf","assets/assets/fonts/Vazirmatn-Regular.ttf","assets/assets/sounds/champion.wav","assets/assets/sounds/flip.wav","assets/assets/sounds/joker.wav","assets/assets/sounds/lose.wav","assets/assets/sounds/perfect.wav","assets/assets/sounds/reveal.wav","assets/assets/sounds/shuffle.wav","assets/assets/sounds/tap.wav","assets/assets/sounds/win.wav","assets/fonts/MaterialIcons-Regular.otf","assets/fonts/fallback/Roboto-Regular.ttf","assets/shaders/ink_sparkle.frag","assets/shaders/stretch_effect.frag","canvaskit/canvaskit.js","canvaskit/canvaskit.wasm","canvaskit/chromium/canvaskit.js","canvaskit/chromium/canvaskit.wasm","favicon.png","flutter.js","flutter_bootstrap.js","icons/Icon-192.png","icons/Icon-512.png","icons/Icon-maskable-192.png","icons/Icon-maskable-512.png","main.dart.js","manifest.json","version.json"] (the files every
// visitor needs). Everything else the page fetches from this site is cached
// on first use, so after one visit the game also works without internet.
const CACHE = 'shahdozd-__VERSION__';
const CORE = __FILES__;

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(CORE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin) return;
  // Pages: network first (new releases show up), cache when offline.
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put('./', copy));
          return res;
        })
        .catch(() => caches.match('./', { ignoreSearch: true })),
    );
    return;
  }
  // Files: cache first, then network (and keep a copy).
  e.respondWith(
    caches.match(req, { ignoreSearch: true }).then(
      (hit) =>
        hit ||
        fetch(req).then((res) => {
          if (res.ok) {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(req, copy));
          }
          return res;
        }),
    ),
  );
});
