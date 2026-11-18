<?php
/* adminpanel/index.php - served on admin.<domain> behind HTTP basic auth over HTTPS.
   Shows full (unmasked) honeypot and login-attempt data. Read-only. */
declare(strict_types=1);
require __DIR__ . '/../config.php';
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

$hits   = rows($conn, 'SELECT id, created_at, ip, method, path, user_agent, username, pw_len, attack_type FROM honeypot_hits ORDER BY id DESC LIMIT 50');
$logins = rows($conn, 'SELECT created_at, ip, username, success FROM login_attempts ORDER BY id DESC LIMIT 50');
$bans   = rows($conn, 'SELECT ip, COUNT(*) AS c FROM honeypot_hits GROUP BY ip ORDER BY c DESC LIMIT 10');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin panel</title>
<style>
  body { font-family: system-ui, Arial, sans-serif; margin:0; padding:20px; background:#f4f6f9; color:#1d2733; }
  h1 { margin-top:0; } h2 { margin:26px 0 8px; font-size:17px; }
  .scroll { overflow-x:auto; background:#fff; border-radius:8px; box-shadow:0 1px 6px rgba(0,0,0,.08); }
  table { border-collapse:collapse; width:100%; font-size:13px; }
  th, td { padding:7px 10px; border-bottom:1px solid #e4e8ee; text-align:left; white-space:nowrap; }
  th { background:#eef2f7; }
  .ok { color:#157347; } .bad { color:#b02a37; }
</style>
</head>
<body>
<h1>Admin panel</h1>
<p>Signed in via HTTP basic auth over TLS. Read-only view of security events.</p>

<h2>Most active honeypot sources</h2>
<div class="scroll"><table>
<tr><th>Address</th><th>Hits</th></tr>
<?php foreach ($bans as $b): ?>
<tr><td><?php echo e($b['ip']); ?></td><td><?php echo (int) $b['c']; ?></td></tr>
<?php endforeach; ?>
<?php if (!$bans): ?><tr><td colspan="2">No hits yet.</td></tr><?php endif; ?>
</table></div>

<h2>Latest honeypot hits (50)</h2>
<div class="scroll"><table>
<tr><th>Time</th><th>Address</th><th>Type</th><th>Request</th><th>User</th><th>Pw length</th><th>User-Agent</th></tr>
<?php foreach ($hits as $h): ?>
<tr>
  <td><?php echo e($h['created_at']); ?></td><td><?php echo e($h['ip']); ?></td>
  <td><?php echo e($h['attack_type']); ?></td><td><?php echo e($h['method'] . ' ' . $h['path']); ?></td>
  <td><?php echo e($h['username']); ?></td><td><?php echo (int) $h['pw_len']; ?></td>
  <td><?php echo e($h['user_agent']); ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$hits): ?><tr><td colspan="7">No hits yet.</td></tr><?php endif; ?>
</table></div>

<h2>Latest real-app login attempts (50)</h2>
<div class="scroll"><table>
<tr><th>Time</th><th>Address</th><th>Username</th><th>Result</th></tr>
<?php foreach ($logins as $l): ?>
<tr>
  <td><?php echo e($l['created_at']); ?></td><td><?php echo e($l['ip']); ?></td><td><?php echo e($l['username']); ?></td>
  <td class="<?php echo $l['success'] ? 'ok' : 'bad'; ?>"><?php echo $l['success'] ? 'success' : 'failed'; ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$logins): ?><tr><td colspan="4">No attempts yet.</td></tr><?php endif; ?>
</table></div>
</body>
</html>
