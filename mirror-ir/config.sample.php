<?php
// Copy to config.php and set USERNAME: the "Document Root" of each
// domain/subdomain in your host panel. Nothing secret here.
// Written for DirectAdmin (subdomains live inside public_html); on cPanel
// use the folders the panel shows for each subdomain.
$home = '/home/USERNAME';                          // the hosting account's home folder
$root = "$home/domains/espadna.ir/public_html";   // espadna.ir

return [
    // Seconds between checks (the cron job can run every minute).
    'interval' => 300,
    // Links inside the copies point to the Iranian addresses. The API has
    // no subdomain on the Iranian host: it is the espadna.ir/api folder.
    // (Longer keys win, so the api lines go before the plain domain.)
    'rewrite' => [
        'https://api.espadna.com' => 'https://espadna.ir/api',
        'api.espadna.com' => 'espadna.ir/api',
        'https://espadna.com' => 'https://espadna.ir',
        'espadna.com' => 'espadna.ir',
    ],
    // Sources use plain http: from Iranian hosts, https to Cloudflare often
    // hangs (connection opens, then no bytes), while http answers at once.
    // Every file is still checked against its sha256 in files.json.
    'sites' => [
        [
            'name' => 'espadna',            // studio site + landing pages
            'source' => 'http://espadna.com',
            'target' => $root,
        ],
        [
            'name' => 'api',                // app settings, word list, privacy
            'type' => 'api',
            'source' => 'http://api.espadna.com',
            'target' => "$root/api",
            'interval' => 120,
        ],
        [
            'name' => 'pantomime',          // the web game: pantomime.espadna.ir
            'source' => 'http://pantomime.espadna.com',
            'target' => "$root/pantomime",
            'spa' => true,
        ],
        [
            'name' => 'tiaro',              // tiaro.espadna.ir
            'source' => 'http://tiaro.espadna.com',
            'target' => "$root/tiaro",
            'spa' => true,
        ],
        [
            'name' => 'tiaro-app',          // Tiaro's landing pages (its own Worker on .com)
            'source' => 'http://espadna.com/tiaro-app',
            'target' => "$root/tiaro-app",
        ],
        // A new app: one more entry, e.g.
        // ['name' => 'x', 'source' => 'https://x.espadna.com', 'target' => "$root/x", 'spa' => true],
    ],
];
