<?php
/* honeypot/index.php - decoy "admin console" served on trap.<domain>.
   Every request is logged to CAT2.honeypot_hits. Real passwords are never stored:
   only the attempted username and the password LENGTH are kept. */
declare(strict_types=1);
require __DIR__ . '/../config.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'");

function clean(string $s, int $max): string
{
    $s = preg_replace('/[^\x20-\x7E]/', '?', $s) ?? '';
    return substr($s, 0, $max);
}

function classify(string $uri, string $ua, string $user, string $pw, string $method): string
{
    $hay = strtolower(rawurldecode($uri) . ' ' . $user . ' ' . $pw);
    if (preg_match('/union\s+select|or\s+1=1|\' or |sleep\(|benchmark\(|--\s|\/\*/', $hay)) {
        return 'sql-injection';
    }
    if (preg_match('/<script|onerror=|javascript:/', $hay)) {
        return 'xss';
    }
    if (preg_match('/\.\.\/|\/etc\/passwd|\x00/', $hay)) {
        return 'path-traversal';
    }
    if (preg_match('/sqlmap|nikto|nmap|masscan|hydra|dirbuster|gobuster|wpscan|nuclei|zgrab|curl|python-requests/i', $ua)) {
        return 'scanner';
    }
    if ($method === 'POST') {
        return 'credential-guess';
    }
    return 'probe';
}

$ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$method = clean($_SERVER['REQUEST_METHOD'] ?? 'GET', 10);
$uri    = clean($_SERVER['REQUEST_URI'] ?? '/', 255);
$ua     = clean($_SERVER['HTTP_USER_AGENT'] ?? '', 255);
$rawPw  = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';
$user   = (isset($_POST['username']) && is_string($_POST['username'])) ? clean($_POST['username'], 100) : '';
$pwlen  = min(strlen($rawPw), 65535);
$path   = parse_url($uri, PHP_URL_PATH) ?: '/';

if ($path === '/favicon.ico') {
    http_response_code(404);
    exit;
}

$type = classify($uri, $ua, $user, $rawPw, $method);

// Flood guard: stop writing after 60 hits per minute from one address.
$recent = 0;
$q = $conn->prepare('SELECT COUNT(*) FROM honeypot_hits WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 MINUTE)');
if ($q) {
    $q->bind_param('s', $ip);
    $q->execute();
    $q->bind_result($recent);
    $q->fetch();
    $q->close();
}
if ($recent < 60) {
    $ins = $conn->prepare(
        'INSERT INTO honeypot_hits (ip, method, path, user_agent, username, pw_len, attack_type)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if ($ins) {
        $ins->bind_param('sssssis', $ip, $method, $uri, $ua, $user, $pwlen, $type);
        $ins->execute();
        $ins->close();
    }
}

// Lure: advertise "hidden" paths the way careless sites do.
if ($path === '/robots.txt') {
    header('Content-Type: text/plain');
    echo "User-agent: *\nDisallow: /admin/\nDisallow: /backup/\nDisallow: /phpmyadmin/\n";
    exit;
}

$failed = ($method === 'POST');
if ($failed) {
    usleep(random_int(700000, 1600000)); // tarpit: slow down guessing
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Administration Console</title>
<style>
  body { margin:0; font-family: system-ui, Arial, sans-serif; background:#eef1f5; color:#1d2733;
         display:flex; min-height:100vh; align-items:center; justify-content:center; }
  .box { background:#fff; width:340px; padding:28px; border-radius:8px; box-shadow:0 2px 14px rgba(0,0,0,.12); }
  h1 { font-size:18px; margin:0 0 4px; }
  p.sub { margin:0 0 18px; font-size:13px; color:#667; }
  label { display:block; font-size:13px; margin:12px 0 4px; }
  input { width:100%; box-sizing:border-box; padding:9px; border:1px solid #c5ccd6; border-radius:4px; font-size:14px; }
  button { margin-top:18px; width:100%; padding:10px; border:0; border-radius:4px; background:#2b6cb0; color:#fff; font-size:15px; cursor:pointer; }
  .err { background:#fde8e8; color:#9b1c1c; padding:8px 10px; border-radius:4px; font-size:13px; margin-bottom:6px; }
</style>
</head>
<body>
<div class="box">
  <h1>Administration Console</h1>
  <p class="sub">Authorised staff only</p>
  <?php if ($failed): ?>
    <div class="err">Invalid credentials. This attempt has been recorded.</div>
  <?php endif; ?>
  <form method="post" action="<?php echo htmlspecialchars($path, ENT_QUOTES, 'UTF-8'); ?>">
    <label for="u">Username</label>
    <input id="u" name="username" type="text" autocomplete="off" maxlength="100">
    <label for="p">Password</label>
    <input id="p" name="password" type="password" autocomplete="off" maxlength="200">
    <button type="submit">Sign in</button>
  </form>
</div>
</body>
</html>
