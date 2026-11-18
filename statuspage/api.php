<?php
/* statuspage/api.php - JSON feed for the public status dashboard.
   Attacker IPs are masked here; the full data lives on the password-protected admin panel. */
declare(strict_types=1);
require __DIR__ . '/../config.php';
date_default_timezone_set('UTC');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function one(mysqli $c, string $sql): int
{
    $r = $c->query($sql);
    if (!$r) {
        return 0;
    }
    $row = $r->fetch_row();
    return (int) ($row[0] ?? 0);
}

function rows(mysqli $c, string $sql): array
{
    $out = [];
    $r = $c->query($sql);
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $out[] = $row;
        }
    }
    return $out;
}

function mask_ip(string $ip): string
{
    if (strpos($ip, ':') !== false) {
        $p = explode(':', $ip);
        return implode(':', array_slice($p, 0, 3)) . '::x';
    }
    $p = explode('.', $ip);
    return count($p) === 4 ? $p[0] . '.' . $p[1] . '.' . $p[2] . '.xxx' : 'unknown';
}

// ---- honeypot statistics ----
$total  = one($conn, 'SELECT COUNT(*) FROM honeypot_hits');
$last24 = one($conn, 'SELECT COUNT(*) FROM honeypot_hits WHERE created_at > (NOW() - INTERVAL 24 HOUR)');
$unique = one($conn, 'SELECT COUNT(DISTINCT ip) FROM honeypot_hits');

$byType = [];
foreach (rows($conn, 'SELECT attack_type, COUNT(*) AS c FROM honeypot_hits GROUP BY attack_type ORDER BY c DESC') as $r) {
    $byType[] = ['type' => $r['attack_type'], 'count' => (int) $r['c']];
}

$top = [];
foreach (rows($conn, 'SELECT ip, COUNT(*) AS c FROM honeypot_hits GROUP BY ip ORDER BY c DESC LIMIT 5') as $r) {
    $top[] = ['ip' => mask_ip($r['ip']), 'count' => (int) $r['c']];
}

$recent = [];
foreach (rows($conn, 'SELECT id, created_at, ip, method, path, username, attack_type FROM honeypot_hits ORDER BY id DESC LIMIT 12') as $r) {
    $recent[] = [
        'id'     => (int) $r['id'],
        'time'   => $r['created_at'],
        'ip'     => mask_ip($r['ip']),
        'method' => $r['method'],
        'path'   => $r['path'],
        'user'   => $r['username'],
        'type'   => $r['attack_type'],
    ];
}

// 24 hourly buckets, built from the database clock so the labels always line up
$nowRow = rows($conn, 'SELECT DATE_FORMAT(NOW(), "%Y-%m-%d %H:%i:%s") AS n');
$now = strtotime(($nowRow[0]['n'] ?? gmdate('Y-m-d H:i:s')) . ' UTC');
$counts = [];
foreach (rows($conn, 'SELECT DATE_FORMAT(created_at, "%Y-%m-%d %H") AS k, COUNT(*) AS c FROM honeypot_hits WHERE created_at > (NOW() - INTERVAL 24 HOUR) GROUP BY k') as $r) {
    $counts[$r['k']] = (int) $r['c'];
}
$hourly = [];
for ($i = 23; $i >= 0; $i--) {
    $t = $now - $i * 3600;
    $hourly[] = ['label' => gmdate('H', $t) . ':00', 'count' => $counts[gmdate('Y-m-d H', $t)] ?? 0];
}

// ---- server health (no shell commands) ----
$load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0, 0, 0]) : [0, 0, 0];
$up = @file_get_contents('/proc/uptime');
$uptime = $up ? (int) floatval(explode(' ', $up)[0]) : 0;

$mem = 0.0;
$mi = @file_get_contents('/proc/meminfo');
if ($mi && preg_match('/MemTotal:\s+(\d+)/', $mi, $a) && preg_match('/MemAvailable:\s+(\d+)/', $mi, $b) && (int) $a[1] > 0) {
    $mem = round(100 - ((int) $b[1] / (int) $a[1]) * 100, 1);
}
$disk = 0.0;
$dt = @disk_total_space('/');
$df = @disk_free_space('/');
if ($dt && $df !== false) {
    $disk = round(100 - ($df / $dt) * 100, 1);
}

// ---- TLS certificate expiry ----
$certFiles = [
    'serveradminproject.me'        => '/etc/ssl/zerossl/fullchain.crt',
    'status.serveradminproject.me' => '/etc/ssl/zerossl/status/fullchain.crt',
    'admin.serveradminproject.me'  => '/etc/ssl/zerossl/admin/fullchain.crt',
];
$certs = [];
foreach ($certFiles as $name => $file) {
    $pem = @file_get_contents($file);
    $x = $pem ? @openssl_x509_parse($pem) : false;
    $certs[] = [
        'name' => $name,
        'days' => $x ? (int) floor(($x['validTo_time_t'] - time()) / 86400) : null,
    ];
}

echo json_encode([
    'generated' => gmdate('c'),
    'honeypot'  => [
        'total'   => $total,
        'last24'  => $last24,
        'unique'  => $unique,
        'by_type' => $byType,
        'top'     => $top,
        'recent'  => $recent,
        'hourly'  => $hourly,
    ],
    'app' => [
        'failed_logins_24h' => one($conn, 'SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at > (NOW() - INTERVAL 24 HOUR)'),
    ],
    'health' => [
        'load'    => [round($load[0], 2), round($load[1], 2), round($load[2], 2)],
        'uptime'  => $uptime,
        'mem_pct' => $mem,
        'disk_pct' => $disk,
        'php'     => PHP_VERSION,
        'db'      => true,
    ],
    'certs' => $certs,
], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
