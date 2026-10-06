<?php
/**
 * Espadna Iranian mirror: keeps copies of the espadna.com sites on an
 * Iranian host so they keep working on Iran's national internet.
 *
 * Run by cron (no SSH needed), e.g. every minute:
 *     php /home/USER/espadna-sync/sync.php
 * Settings: config.php next to this file (see config.sample.php).
 *
 * For every site in config.php:
 *   - type "files": downloads <source>/files.json (path + sha256 of every
 *     file), fetches only the files that changed, verifies each hash, writes
 *     it in place (atomic rename), removes files that disappeared, rewrites
 *     espadna.com → espadna.ir in text files, and turns _redirects into
 *     .htaccess rules.
 *   - type "api": copies config.json / words_fa.json after checking them
 *     (valid JSON, never an older version) and the privacy page.
 * When the source is unreachable (national internet) nothing is touched:
 * the last good copy keeps being served.
 *
 * status.json (next to this file, and copied to each site as
 * mirror-status.json) says when each site was last checked and updated.
 */

declare(strict_types=1);

const USER_AGENT = 'espadna-mirror/1';
const TEXT_EXT = ['html', 'htm', 'js', 'mjs', 'json', 'css', 'xml', 'txt', 'svg', 'webmanifest'];

$dir = __DIR__;
$cfg = require $dir . '/config.php';
$force = in_array('--force', $argv ?? [], true);

// One run at a time (cron fires every minute, a big update takes longer).
$lock = fopen($dir . '/sync.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
set_time_limit(0);

$statusFile = $dir . '/status.json';
$status = is_file($statusFile) ? (json_decode((string) file_get_contents($statusFile), true) ?: []) : [];

function logline(string $msg): void
{
    global $dir;
    $line = gmdate('Y-m-d H:i:s') . ' ' . $msg . "\n";
    $file = $dir . '/sync.log';
    if (is_file($file) && filesize($file) > 512 * 1024) {
        $tail = array_slice(file($file) ?: [], -2000);
        file_put_contents($file, implode('', $tail));
    }
    file_put_contents($file, $line, FILE_APPEND);
    if (PHP_SAPI === 'cli') {
        echo $line;
    }
}

/** Why the last http_get() failed, for status.json. */
$httpError = '';

function http_get(string $url, int $timeout): ?string
{
    global $httpError;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => ['Cache-Control: no-cache'],
        // Many Iranian hosts have no working IPv6 route; Cloudflare offers both.
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) {
        $httpError = 'curl ' . curl_errno($ch) . ': ' . curl_error($ch);
    } elseif ($code !== 200) {
        $httpError = "HTTP $code";
    }
    curl_close($ch);
    return ($body !== false && $code === 200) ? $body : null;
}

/** Writes $data to $path atomically (temp file + rename), creating folders. */
function put_file(string $path, string $data): bool
{
    $d = dirname($path);
    if (!is_dir($d) && !mkdir($d, 0755, true) && !is_dir($d)) {
        return false;
    }
    $tmp = $path . '.mirror-tmp';
    if (file_put_contents($tmp, $data) === false) {
        return false;
    }
    return rename($tmp, $path);
}

function safe_rel(string $rel): bool
{
    return $rel !== '' && $rel[0] !== '/' && strpos($rel, '..') === false && strpos($rel, "\0") === false
        && strpos($rel, '.mirror') === false;
}

function rewrite(string $rel, string $data, array $map): string
{
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (!$map || !in_array($ext, TEXT_EXT, true)) {
        return $data;
    }
    // canonical / hreflang links keep pointing at the main (.com) pages, so
    // search engines treat the copy as a mirror, not as duplicate content.
    $keep = [];
    if ($ext === 'html' || $ext === 'htm') {
        $data = preg_replace_callback(
            '/<link\b[^>]*\brel="(?:canonical|alternate)"[^>]*>/i',
            function (array $m) use (&$keep): string {
                $keep[] = $m[0];
                return "\0keep" . (count($keep) - 1) . "\0";
            },
            $data
        );
    }
    $data = strtr($data, $map);
    foreach ($keep as $i => $tag) {
        $data = str_replace("\0keep$i\0", $tag, $data);
    }
    return $data;
}

/** Cloudflare _redirects ("from to [code]") → Apache rewrite rules. */
function redirects_to_htaccess(string $redirects, array $map): string
{
    $rules = [];
    foreach (preg_split('/\R/', $redirects) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $parts = preg_split('/\s+/', $line);
        if (count($parts) < 2) {
            continue;
        }
        [$from, $to] = $parts;
        $code = $parts[2] ?? '302';
        $to = strtr($to, $map);
        $pattern = '^' . preg_quote(ltrim($from, '/'), '#');
        if (substr($pattern, -2) === '\*') {
            $pattern = substr($pattern, 0, -2) . '(.*)';
            $to = str_replace(':splat', '$1', $to);
        }
        $rules[] = "RewriteRule {$pattern}$ {$to} [R={$code},L]";
    }
    return $rules ? implode("\n", $rules) . "\n" : '';
}

/**
 * $spa: unknown paths serve index.html (single-page web apps).
 * RewriteEngine On is always set: it also stops the main site's rules (in a
 * parent folder's .htaccess) from applying to a subdomain folder inside it.
 */
function write_htaccess(string $target, string $extra, bool $spa = false): void
{
    $notFound = $spa
        ? "RewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^ index.html [L]\n"
        : '';
    $base = <<<'HT'
# Written by espadna-mirror (sync.php); changes here are overwritten.
DirectoryIndex index.html
ErrorDocument 404 /404.html
AddType application/wasm .wasm
AddType application/javascript .js .mjs
AddType application/json .json
AddType application/manifest+json .webmanifest
AddType font/ttf .ttf
AddType image/svg+xml .svg
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css text/plain application/javascript application/json application/wasm font/ttf image/svg+xml application/xml
</IfModule>
<IfModule mod_headers.c>
  Header set Access-Control-Allow-Origin "*"
  <FilesMatch "\.(html|json)$|^(sw|main\.dart|flutter_bootstrap)\.js$">
    Header set Cache-Control "no-cache"
  </FilesMatch>
  <FilesMatch "\.(wasm|ttf|png|webp|jpg|svg)$">
    Header set Cache-Control "public, max-age=604800"
  </FilesMatch>
</IfModule>
<Files "mirror-manifest.json">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Deny from all
  </IfModule>
</Files>

RewriteEngine On

HT;
    put_file($target . '/.htaccess', $base . $extra . $notFound);
}

/**
 * Downloads one file, resuming with Range requests when the connection breaks
 * mid-way (common on Iranian links to foreign servers). No compression, so
 * byte offsets stay valid.
 */
function http_get_file(string $url, int $size): ?string
{
    global $httpError;
    $data = '';
    // Keep resuming while each try brings new bytes; stop after 3 tries
    // in a row without progress.
    for ($try = 0, $stuck = 0; $try < 500 && $stuck < 3; $try++) {
        $before = strlen($data);
        $chunk = '';
        $ch = curl_init($url);
        $opts = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 300,
            // Give up on a stalled transfer (< 1 KB/s for 20 s) and resume.
            CURLOPT_LOW_SPEED_LIMIT => 1024,
            CURLOPT_LOW_SPEED_TIME => 20,
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => ['Cache-Control: no-cache'],
            CURLOPT_WRITEFUNCTION => function ($ch, string $s) use (&$chunk): int {
                $chunk .= $s;
                return strlen($s);
            },
        ];
        if ($data !== '') {
            $opts[CURLOPT_RANGE] = strlen($data) . '-';
        }
        curl_setopt_array($ch, $opts);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = $ok === false ? 'curl ' . curl_errno($ch) . ': ' . curl_error($ch) : '';
        curl_close($ch);
        if ($code === 206 && $data !== '') {
            $data .= $chunk;
        } elseif ($code === 200) {
            $data = $chunk; // a full answer (the server ignored the range)
        } else {
            $httpError = $err !== '' ? $err : "HTTP $code";
            $data = '';
            $stuck++;
            continue;
        }
        $stuck = strlen($data) > $before ? 0 : $stuck + 1;
        if ($ok !== false || strlen($data) >= $size) {
            return $data;
        }
        $httpError = "$err after " . strlen($data) . " of $size bytes";
    }
    return null;
}

function sync_files(array $site, array $map, bool $force): string
{
    $src = rtrim($site['source'], '/');
    $target = rtrim($site['target'], '/');
    global $httpError;
    $raw = http_get($src . '/files.json', 30);
    if ($raw === null) {
        return "unreachable ($httpError)";
    }
    $manifest = json_decode($raw, true);
    if (!is_array($manifest) || !isset($manifest['files']) || !is_array($manifest['files'])) {
        return 'source has no files.json yet';
    }
    $localFile = $target . '/mirror-manifest.json';
    $local = is_file($localFile) ? (json_decode((string) file_get_contents($localFile), true) ?: []) : [];
    $sameMap = ($local['map'] ?? null) === $map;
    if (!$force && $sameMap && ($local['version'] ?? null) === ($manifest['version'] ?? '')) {
        return 'up to date';
    }
    // Files already in place (from the last complete sync, or from an
    // interrupted one: progress is saved after every file).
    $have = [];
    if ($sameMap) {
        foreach ($local['files'] ?? [] as $f) {
            $have[$f['path']] = $f['sha256'];
        }
    }
    $old = $have;
    $saveProgress = function () use (&$have, $localFile, $map): void {
        $files = [];
        foreach ($have as $p => $h) {
            $files[] = ['path' => $p, 'sha256' => $h];
        }
        put_file($localFile, json_encode(['version' => 'partial', 'map' => $map, 'files' => $files]));
    };
    $fetched = 0;
    $failed = [];
    foreach ($manifest['files'] as $f) {
        $rel = (string) ($f['path'] ?? '');
        if (!safe_rel($rel) || $rel === '_redirects') {
            continue;
        }
        $path = $target . '/' . $rel;
        if (!$force && ($have[$rel] ?? '') === $f['sha256'] && is_file($path)) {
            continue;
        }
        $data = http_get_file($src . '/' . str_replace('%2F', '/', rawurlencode($rel)), (int) ($f['size'] ?? 0));
        if ($data === null || hash('sha256', $data) !== $f['sha256']) {
            // The old copy (if any) stays; this file is retried next run.
            $failed[] = $rel . ' (' . ($data === null ? $httpError : 'changed or damaged in transit') . ')';
            continue;
        }
        if (!put_file($path, rewrite($rel, $data, $map))) {
            $failed[] = "$rel (write failed)";
            continue;
        }
        $have[$rel] = $f['sha256'];
        $saveProgress();
        $fetched++;
    }
    // files.json carries the _redirects text (Cloudflare does not serve the file).
    $redirects = is_string($manifest['redirects'] ?? null) ? $manifest['redirects'] : '';
    write_htaccess($target, redirects_to_htaccess($redirects, $map), !empty($site['spa']));
    if ($failed) {
        return 'incomplete: ' . count($failed) . " files left, got $fetched; first: " . $failed[0];
    }
    // Files the source no longer has.
    $now = array_column($manifest['files'], 'path');
    foreach (array_diff(array_keys($old), $now) as $gone) {
        if (safe_rel($gone) && is_file($target . '/' . $gone)) {
            unlink($target . '/' . $gone);
        }
    }
    $manifest['map'] = $map;
    put_file($localFile, json_encode($manifest));
    return "updated to {$manifest['version']} ({$fetched} files)";
}

function json_version(?string $raw, string $key): ?int
{
    $d = $raw === null ? null : json_decode($raw, true);
    return is_array($d) && isset($d[$key]) && is_int($d[$key]) ? $d[$key] : null;
}

function sync_api(array $site, array $map): string
{
    $src = rtrim($site['source'], '/');
    $target = rtrim($site['target'], '/');
    $done = [];
    foreach (['config.json' => 'config_version', 'words_fa.json' => 'version'] as $file => $key) {
        $raw = http_get("$src/$file", 30);
        $new = json_version($raw, $key);
        if ($new === null) {
            global $httpError;
            $done[] = "$file unreachable" . ($raw === null ? " ($httpError)" : ' (not valid)');
            continue;
        }
        $have = is_file("$target/$file") ? json_version((string) file_get_contents("$target/$file"), $key) : null;
        if ($have !== null && $new < $have) {
            $done[] = "$file kept v$have";
            continue;
        }
        if ($have !== $new) {
            put_file("$target/$file", $raw);
            $done[] = "$file v$new";
        }
    }
    $privacy = http_get("$src/privacy", 30);
    if ($privacy !== null && stripos($privacy, '<html') !== false) {
        put_file("$target/privacy.html", strtr($privacy, $map));
    }
    write_htaccess($target, "RewriteRule ^privacy/?$ privacy.html [L]\n");
    return $done ? implode(', ', $done) : 'up to date';
}

foreach ($cfg['sites'] as $site) {
    $name = $site['name'];
    $interval = (int) ($site['interval'] ?? $cfg['interval'] ?? 300);
    $last = (int) ($status[$name]['checked_at'] ?? 0);
    if (!$force && time() - $last < $interval) {
        continue;
    }
    if (!is_dir($site['target']) && !mkdir($site['target'], 0755, true)) {
        logline("$name: target folder missing: {$site['target']}");
        continue;
    }
    $map = $site['rewrite'] ?? $cfg['rewrite'] ?? [];
    $result = ($site['type'] ?? 'files') === 'api' ? sync_api($site, $map) : sync_files($site, $map, $force);
    $status[$name]['checked_at'] = time();
    $status[$name]['checked'] = gmdate('c');
    $status[$name]['result'] = $result;
    if (strpos($result, 'updated') === 0 || strpos($result, ' v') !== false) {
        $status[$name]['updated'] = gmdate('c');
    }
    if ($result !== 'up to date') {
        logline("$name: $result");
    }
    put_file(rtrim($site['target'], '/') . '/mirror-status.json', json_encode($status[$name], JSON_PRETTY_PRINT));
}
put_file($statusFile, json_encode($status, JSON_PRETTY_PRINT));
