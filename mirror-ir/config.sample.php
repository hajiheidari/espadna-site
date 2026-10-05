<?php
// Copy to config.php and set the folders: the "Document Root" of each
// domain/subdomain in your host panel. Nothing secret here.
// Keep subdomain folders OUTSIDE the main site's folder (not public_html/x),
// otherwise the main site's redirect rules also apply to them.
$home = '/home/USERNAME'; // your hosting account's home folder

return [
    // Seconds between checks (the cron job can run every minute).
    'interval' => 300,
    // Links inside the copies point to the Iranian domains.
    'rewrite' => [
        'https://espadna.com' => 'https://espadna.ir',
        'espadna.com' => 'espadna.ir',
    ],
    'sites' => [
        [
            'name' => 'espadna',            // studio site + landing pages
            'source' => 'https://espadna.com',
            'target' => "$home/public_html",
        ],
        [
            'name' => 'pantomime',          // the web game
            'source' => 'https://pantomime.espadna.com',
            'target' => "$home/pantomime.espadna.ir",
        ],
        [
            'name' => 'api',                // app settings, word list, privacy
            'type' => 'api',
            'source' => 'https://api.espadna.com',
            'target' => "$home/api.espadna.ir",
            'interval' => 120,
        ],
        // A new app: one more entry, e.g.
        // ['name' => 'tiaro', 'source' => 'https://tiaro.espadna.com', 'target' => "$home/tiaro.espadna.ir"],
    ],
];
