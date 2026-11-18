#!/usr/bin/env bash
# deploy/finish.sh - final root-level hardening for IS Project 1.
# Run on the server:   cd /var/www/app && git pull && sudo bash deploy/finish.sh
#
# Steps (each one is independent; a failure is reported and the script moves on):
#   0. app file ownership: owner:www-data, setgid directories, nothing for "other"
#   1. nginx: new site file (www over HTTPS, ACME webroot, login rate limit, blocked internals)
#   2. ZeroSSL certificate for apex + www via ACME (acme.sh), with automatic renewal
#   3. nginx global TLS clean-up (drop TLS 1.0/1.1 defaults, hide version)
#   4. SSH hardening (keys only, no root, 3 tries) + confirm fail2ban sshd jail
#   5. UFW logging + nightly database/config backups + daily certificate-expiry check
#   6. Optional: rotate the admin-panel password, reboot for the pending kernel
set -uo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Run with sudo: sudo bash $0"; exit 1; }

APP=/var/www/app
DOMAIN=serveradminproject.me
STAMP=$(date +%Y%m%d-%H%M%S)
BK=/root/isproject-backup-$STAMP
mkdir -p "$BK"
RESULTS=()
step() {  # step "label" function
    echo; echo "=== $1"
    if "$2"; then RESULTS+=("OK      $1"); else RESULTS+=("FAILED  $1"); fi
}

nginx_site() {
    cp /etc/nginx/sites-available/app "$BK/app.nginx"
    mkdir -p /var/www/acme && chown www-data:www-data /var/www/acme
    cp "$APP/deploy/nginx-app.conf" /etc/nginx/sites-available/app
    if nginx -t; then
        systemctl reload nginx
    else
        echo "nginx test failed - restoring previous site file"
        cp "$BK/app.nginx" /etc/nginx/sites-available/app
        return 1
    fi
}

zerossl_acme() {
    local ACME=/root/.acme.sh/acme.sh EMAIL
    read -r -p "E-mail for the ZeroSSL ACME account (expiry notices): " EMAIL
    [ -n "$EMAIL" ] || { echo "no e-mail given - skipped"; return 1; }
    if [ ! -x "$ACME" ]; then
        local tmp; tmp=$(mktemp -d)
        git clone --depth 1 --branch 3.1.6 https://github.com/acmesh-official/acme.sh.git "$tmp/acme.sh" || return 1
        (cd "$tmp/acme.sh" && ./acme.sh --install -m "$EMAIL" --home /root/.acme.sh) || return 1
        rm -rf "$tmp"
    fi
    "$ACME" --set-default-ca --server zerossl
    "$ACME" --register-account -m "$EMAIL" --server zerossl || return 1
    "$ACME" --issue --server zerossl -d "$DOMAIN" -d "www.$DOMAIN" -w /var/www/acme --keylength 2048
    local rc=$?
    [ $rc -eq 0 ] || [ $rc -eq 2 ] || return 1   # 2 = already valid, nothing to renew
    cp -a /etc/ssl/zerossl/fullchain.crt /etc/ssl/zerossl/private.key "$BK/" 2>/dev/null
    "$ACME" --install-cert -d "$DOMAIN" \
        --key-file       /etc/ssl/zerossl/private.key \
        --fullchain-file /etc/ssl/zerossl/fullchain.crt \
        --reloadcmd      "chmod 600 /etc/ssl/zerossl/private.key && systemctl reload nginx" || return 1
    echo | openssl s_client -connect 127.0.0.1:443 -servername "www.$DOMAIN" 2>/dev/null \
        | openssl x509 -noout -issuer -ext subjectAltName -enddate
}

nginx_global() {
    cp /etc/nginx/nginx.conf "$BK/nginx.conf"
    sed -i -E 's/^(\s*)ssl_protocols .*/\1ssl_protocols TLSv1.2 TLSv1.3;/' /etc/nginx/nginx.conf
    sed -i -E 's/^(\s*)# ?server_tokens off;/\1server_tokens off;/' /etc/nginx/nginx.conf
    nginx -t && systemctl reload nginx || { cp "$BK/nginx.conf" /etc/nginx/nginx.conf; return 1; }
}

ssh_hardening() {
    # 01- sorts before Ubuntu's 50-cloudimg-settings.conf, and sshd keeps the first value it reads
    cat > /etc/ssh/sshd_config.d/01-isproject-hardening.conf <<'CONF'
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
MaxAuthTries 3
LoginGraceTime 30
X11Forwarding no
ClientAliveInterval 300
ClientAliveCountMax 2
CONF
    if sshd -t; then
        systemctl reload ssh
        sshd -T | grep -E '^(permitrootlogin|passwordauthentication|maxauthtries) '
    else
        rm -f /etc/ssh/sshd_config.d/01-isproject-hardening.conf; return 1
    fi
    fail2ban-client status sshd >/dev/null 2>&1 || {
        printf '[sshd]\nenabled = true\nmaxretry = 4\nbantime = 1h\n' > /etc/fail2ban/jail.d/sshd.local
        systemctl restart fail2ban; sleep 2
    }
    fail2ban-client status sshd
}

ops() {
    ufw logging on
    install -m 750 "$APP/deploy/backup.sh"     /usr/local/sbin/isproject-backup
    install -m 755 "$APP/deploy/cert-check.sh" /usr/local/sbin/isproject-cert-check
    cat > /etc/cron.d/isproject <<'CRON'
# IS Project 1 maintenance jobs
30 2 * * * root /usr/local/sbin/isproject-backup
0  7 * * * root /usr/local/sbin/isproject-cert-check
CRON
    /usr/local/sbin/isproject-backup && ls -lh /var/backups/isproject | tail -3
    /usr/local/sbin/isproject-cert-check
}

admin_password() {
    local a; read -r -p "Rotate the admin-panel password now? [y/N] " a
    [ "${a,,}" = y ] || { echo "skipped"; return 0; }
    local pw; pw=$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)
    cp /etc/nginx/.htpasswd "$BK/htpasswd"
    printf 'admin:%s\n' "$(openssl passwd -apr1 "$pw")" > /etc/nginx/.htpasswd
    chown root:www-data /etc/nginx/.htpasswd && chmod 640 /etc/nginx/.htpasswd
    echo "New admin password (shown once, store it in a password manager): $pw"
}

permissions() {
    # setgid directories: files that git pull rewrites inherit the www-data group,
    # so the web server can read them while "other" users get nothing.
    local owner; owner=$(stat -c %U "$APP")
    chown -R "$owner":www-data "$APP"
    find "$APP" -type d -exec chmod 2750 {} +
    find "$APP" -type f -exec chmod g+r,o-rwx {} +
    chmod 640 "$APP"/config.php
}

step "app file permissions"      permissions
step "nginx site file"           nginx_site
step "ZeroSSL apex+www via ACME" zerossl_acme
step "nginx global TLS"          nginx_global
step "SSH hardening + fail2ban"  ssh_hardening
step "backups, cert check, UFW"  ops
step "admin password"            admin_password

echo; echo "=== smoke tests"
for u in "http://$DOMAIN" "https://$DOMAIN/login.php" "https://www.$DOMAIN" "https://$DOMAIN/config.php" \
         "https://status.$DOMAIN/" "https://admin.$DOMAIN/"; do
    printf '%-42s %s\n' "$u" "$(curl -s -o /dev/null -w '%{http_code}' "$u")"
done
echo "expected: 301, 200, 301, 404, 200, 401"

echo; echo "=== summary (backups of replaced files: $BK)"
printf '%s\n' "${RESULTS[@]}"
if [ -f /var/run/reboot-required ]; then
    read -r -p "A reboot is pending for the new kernel. Reboot now? [y/N] " a
    [ "${a,,}" = y ] && reboot
fi
