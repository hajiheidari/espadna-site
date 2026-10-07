<?php
// Copy to config.php and set USERNAME: the "Document Root" of each
// domain/subdomain in your host panel. Nothing secret here.
// Written for DirectAdmin (subdomains live inside public_html); on cPanel
// use the folders the panel shows for each subdomain.
$home = '/home/USERNAME';                          // the hosting account's home folder
$root = "$home/domains/espadna.ir/public_html";   // espadna.ir
$git = 'https://raw.githubusercontent.com/hajiheidari/espadna-site/mirror';

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
    // Sources: the `mirror` branch of the espadna-site repository on GitHub
    // (refreshed every 10 minutes by .github/workflows/mirror-pack.yml).
    // From Iranian hosts, downloads from Cloudflare stall after ~63 KB,
    // while GitHub works. Every file is checked against its sha256.
    'sites' => [
        [
            'name' => 'espadna',            // studio site + landing pages
            'source' => "$git/espadna",
            'target' => $root,
        ],
        [
            'name' => 'api',                // app settings, word list, privacy
            'type' => 'api',
            'source' => "$git/api",
            'target' => "$root/api",
            'interval' => 120,
        ],
        [
            'name' => 'pantomime',          // the web game: pantomime.espadna.ir
            'source' => "$git/pantomime",
            'target' => "$root/pantomime",
            'spa' => true,
        ],
        [
            'name' => 'tiaro',              // tiaro.espadna.ir
            'source' => "$git/tiaro",
            'target' => "$root/tiaro",
            'spa' => true,
        ],
        [
            'name' => 'tiaro-app',          // Tiaro's landing pages (its own Worker on .com)
            'source' => "$git/tiaro-app",
            'target' => "$root/tiaro-app",
        ],
        // A new app: one more entry, e.g.
        // (and the site in tools/mirror_pack.py)
        // ['name' => 'x', 'source' => "$git/x", 'target' => "$root/x", 'spa' => true],
    ],
];
