<?php
/* security.php - shared helpers: output escaping, CSRF, password policy,
   breached-password check (HIBP k-anonymity) and login rate limiting. */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post_str(string $k): string
{
    return (isset($_POST[$k]) && is_string($_POST[$k])) ? trim($_POST[$k]) : '';
}

function post_raw(string $k): string
{
    return (isset($_POST[$k]) && is_string($_POST[$k])) ? $_POST[$k] : '';
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_ok(): bool
{
    return isset($_SESSION['csrf'], $_POST['csrf'])
        && is_string($_POST['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function password_problems(string $p, string $username = ''): array
{
    $errs = [];
    if (strlen($p) < 12) {
        $errs[] = 'Password must be at least 12 characters.';
    }
    if (strlen($p) > 128) {
        $errs[] = 'Password must be at most 128 characters.';
    }
    if (!preg_match('/[a-z]/', $p)) {
        $errs[] = 'Password needs a lowercase letter.';
    }
    if (!preg_match('/[A-Z]/', $p)) {
        $errs[] = 'Password needs an uppercase letter.';
    }
    if (!preg_match('/\d/', $p)) {
        $errs[] = 'Password needs a digit.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $p)) {
        $errs[] = 'Password needs a symbol.';
    }
    if ($username !== '' && stripos($p, $username) !== false) {
        $errs[] = 'Password must not contain your username.';
    }
    return $errs;
}

/* Have I Been Pwned: only the first 5 characters of the SHA-1 leave the server.
   Fails open (returns 0) if the API is unreachable. */
function pwned_count(string $p): int
{
    $sha = strtoupper(sha1($p));
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 3,
            'header'  => "User-Agent: IS-Project1\r\nAdd-Padding: true\r\n",
        ],
    ]);
    $body = @file_get_contents('https://api.pwnedpasswords.com/range/' . substr($sha, 0, 5), false, $ctx);
    if ($body === false) {
        return 0;
    }
    foreach (explode("\n", $body) as $line) {
        $parts = explode(':', trim($line));
        if (count($parts) === 2 && hash_equals(substr($sha, 5), strtoupper($parts[0]))) {
            return (int) $parts[1];
        }
    }
    return 0;
}

function login_locked(mysqli $c, string $user): bool
{
    $ip   = client_ip();
    $user = substr($user, 0, 60);
    $s = $c->prepare(
        'SELECT COUNT(*), COALESCE(SUM(username = ?), 0) FROM login_attempts
         WHERE ip = ? AND success = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)'
    );
    if (!$s) {
        return false;
    }
    $s->bind_param('ss', $user, $ip);
    $s->execute();
    $s->bind_result($total, $mine);
    $s->fetch();
    $s->close();
    return $mine >= 5 || $total >= 20;
}

function log_attempt(mysqli $c, string $user, bool $ok): void
{
    $ip   = client_ip();
    $user = substr($user, 0, 60);
    $f    = $ok ? 1 : 0;
    $s = $c->prepare('INSERT INTO login_attempts (ip, username, success) VALUES (?, ?, ?)');
    if ($s) {
        $s->bind_param('ssi', $ip, $user, $f);
        $s->execute();
        $s->close();
    }
}

function clear_failures(mysqli $c, string $user): void
{
    $ip   = client_ip();
    $user = substr($user, 0, 60);
    $s = $c->prepare('DELETE FROM login_attempts WHERE ip = ? AND username = ? AND success = 0');
    if ($s) {
        $s->bind_param('ss', $ip, $user);
        $s->execute();
        $s->close();
    }
}
