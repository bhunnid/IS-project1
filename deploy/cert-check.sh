#!/usr/bin/env bash
# deploy/cert-check.sh -> /usr/local/sbin/isproject-cert-check (run daily by /etc/cron.d/isproject)
# Reads the certificate each virtual host actually serves and warns 21 days before expiry.
set -uo pipefail
WARN_DAYS=21
LOG=/var/log/isproject-certcheck.log
for h in serveradminproject.me www.serveradminproject.me admin.serveradminproject.me status.serveradminproject.me; do
    end=$(echo | openssl s_client -connect 127.0.0.1:443 -servername "$h" 2>/dev/null \
          | openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2)
    if [ -z "$end" ]; then
        msg="$h: could not read certificate"
    else
        days=$(( ( $(date -d "$end" +%s) - $(date +%s) ) / 86400 ))
        msg="$h: $days days left (expires $end)"
        [ "$days" -lt "$WARN_DAYS" ] && msg="WARNING $msg"
    fi
    echo "$(date '+%F %T') $msg" | tee -a "$LOG"
    case "$msg" in WARNING*|*could*) logger -p user.warning -t isproject-cert-check "$msg" ;; esac
done
