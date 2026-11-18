# IS Project 1 — Secure, Self-Defending PHP Web Application

CNS 3205 Server Administration end-of-semester project, Strathmore University (CNS 3A).
**Team:** Brandon Kiplangat (193310) · Ian Muyala (191616)

| Service | URL | Purpose |
|---------|-----|---------|
| Main app | https://serveradminproject.me | Signup, login, dashboard, feedback |
| Admin | https://admin.serveradminproject.me | Security events (HTTP basic auth over TLS) |
| Status | https://status.serveradminproject.me | Live honeypot, health and certificate dashboard |
| Honeypot | http://trap.serveradminproject.me | Decoy "Administration Console" that logs attackers |

## Architecture
```
Internet ──> Azure DNS (serveradminproject.me zone, 4 azure-dns name servers)
         ──> Azure NSG (22, 80, 443) ──> UFW ──> fail2ban
         ──> Nginx 1.24 (4 virtual hosts, ZeroSSL TLS 1.2/1.3, security headers, rate limit)
         ──> PHP 8.3-FPM ──> MariaDB 10.11 (database CAT2, least-privilege user cat2_user)
Azure VM: Ubuntu 24.04 LTS, Standard_B2ats_v2, Central India, static IP 135.235.194.239
```

## Security features
- Argon2id password hashing, automatic rehash of old bcrypt hashes on login
- Prepared statements everywhere; output escaped with `htmlspecialchars`
- CSRF tokens on every form; HTML5 + server-side validation; 12+ character password policy
- Breached-password check (Have I Been Pwned k-anonymity: only 5 hash characters leave the server)
- Login rate limiting in PHP (5 failures / 15 min) and in Nginx (10 requests/min per IP)
- Hardened sessions (Secure, HttpOnly, SameSite=Strict, strict mode, 30-minute idle timeout)
- Strict Content-Security-Policy, HSTS, X-Frame-Options, nosniff, Referrer/Permissions-Policy, SRI on CDN assets
- DB credentials outside the web root and outside Git (`/etc/isproject/db.php`, mode 640)
- Honeypot + fail2ban auto-ban, live status page, admin panel
- Nightly DB/config backups, daily certificate-expiry check, CI with PHP lint and gitleaks secret scanning

## Deploy
```bash
sudo mysql < db/cat2_setup.sql && sudo mysql CAT2 < db/login_attempts.sql
cd /var/www/app && git pull
bash deploy/apply.sh          # honeypot, status page, admin panel, fail2ban jail
sudo bash deploy/finish.sh    # www certificate, SSH hardening, backups, cert monitor
```

## Layout
```
register.php login.php dashboard.php feedback.php logout.php   application pages
security.php config.php partials/                              include-only (blocked from the web)
honeypot/ statuspage/ adminpanel/                              trap, status and admin virtual hosts
db/                                                            schema scripts
deploy/                                                        nginx site, fail2ban, installers, cron scripts
server/99-hardening.ini                                        PHP session and error hardening
```
