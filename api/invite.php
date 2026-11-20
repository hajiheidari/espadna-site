<?php
/**
 * espadna-invite: invite link visits on the Iranian host (no database).
 * Same rules as the Cloudflare Worker (pantomime repo, server/src/invite.js):
 *
 *   GET  invite/<code>  → {"visits": n}; remembers the caller as the owner
 *   POST invite/<code>  → counts one visit (once per visitor, never the owner)
 *
 * Installed and updated by sync.php (from the GitHub mirror); .htaccess maps
 * invite/<code> to invite.php?c=<code>. Visitors are kept as a hash of their
 * IP mixed with the code, never the raw IP.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

$code = isset($_GET['c']) ? (string) $_GET['c'] : '';
if (!preg_match('/^[A-Z2-9]{6,12}$/', $code)) {
    http_response_code(400);
    exit('{"error":"bad code"}');
}

$dir = __DIR__ . '/invite-data';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
if (!is_file("$dir/.htaccess")) {
    @file_put_contents("$dir/.htaccess", "Require all denied\nDeny from all\n");
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
$me = substr(hash('sha256', "$code|$ip"), 0, 16);
$file = "$dir/$code.json";

$fp = @fopen($file, 'c+');
if ($fp === false) {
    http_response_code(503);
    exit('{"error":"storage"}');
}
flock($fp, LOCK_EX);
$raw = stream_get_contents($fp);
$rec = $raw ? json_decode($raw, true) : null;
if (!is_array($rec)) {
    $rec = array('n' => 0, 'h' => array(), 'o' => null);
}
$changed = false;
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

if ($method === 'POST') {
    if ($me !== $rec['o'] && !in_array($me, $rec['h'], true)) {
        $rec['n'] += 1;
        $rec['h'][] = $me;
        $rec['h'] = array_slice($rec['h'], -50);
        $changed = true;
    }
    http_response_code(204);
    $out = '';
} else {
    if ($rec['o'] !== $me) {
        $rec['o'] = $me;
        $changed = true;
    }
    $out = json_encode(array('visits' => (int) $rec['n']));
}
if ($changed) {
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($rec));
    fflush($fp);
}
flock($fp, LOCK_UN);
fclose($fp);
echo $out;
