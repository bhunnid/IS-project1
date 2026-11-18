#!/usr/bin/env bash
# One-shot installer for the honeypot, status page, admin panel and fail2ban jail.
# Run on the server as your normal user:  bash /var/www/app/deploy/apply.sh
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

for f in ssl-zerossl.conf ssl-common.conf phpapp.conf; do
  [ -f "/etc/nginx/snippets/$f" ] || { echo "Missing /etc/nginx/snippets/$f - create it first"; exit 1; }
done

echo "[1/6] PHP syntax check"
for f in honeypot/index.php statuspage/api.php adminpanel/index.php; do php -l "$f"; done

echo "[2/6] database table"
sudo mysql CAT2 < db/honeypot.sql

echo "[3/6] file permissions"
sudo chown -R "$(id -un)":www-data /var/www/app
sudo chmod -R g+rX,o-rwx /var/www/app

echo "[4/6] nginx"
sudo cp /etc/nginx/sites-available/app /root/app.nginx.bak 2>/dev/null || true
sudo cp deploy/nginx-app.conf /etc/nginx/sites-available/app
sudo ln -sf /etc/nginx/sites-available/app /etc/nginx/sites-enabled/app
sudo nginx -t
sudo systemctl reload nginx

echo "[5/6] fail2ban jail"
sudo cp deploy/fail2ban/trap-honeypot.filter.conf /etc/fail2ban/filter.d/trap-honeypot.conf
sudo cp deploy/fail2ban/trap-honeypot.jail.local /etc/fail2ban/jail.d/trap-honeypot.local
sudo touch /var/log/nginx/trap.access.log
sudo systemctl restart fail2ban
sleep 2
sudo fail2ban-client status trap-honeypot || true

echo "[6/6] smoke tests"
echo -n "trap   (expect 200): "; curl -s -o /dev/null -w "%{http_code}\n" -H "Host: trap.serveradminproject.me" http://127.0.0.1/admin
echo -n "status (expect 200): "; curl -sk -o /dev/null -w "%{http_code}\n" https://status.serveradminproject.me/
echo -n "api    (expect 200): "; curl -sk -o /dev/null -w "%{http_code}\n" https://status.serveradminproject.me/api.php
echo -n "admin  (expect 401): "; curl -sk -o /dev/null -w "%{http_code}\n" https://admin.serveradminproject.me/
echo -n "main   (expect 404 on /honeypot/): "; curl -sk -o /dev/null -w "%{http_code}\n" https://serveradminproject.me/honeypot/index.php
echo "Done."
