#!/usr/bin/env bash
# deploy/backup.sh -> /usr/local/sbin/isproject-backup (run nightly by /etc/cron.d/isproject)
# Dumps the CAT2 database and the server configuration, keeps 14 days of archives.
set -euo pipefail
DEST=/var/backups/isproject
DAY=$(date +%F)
umask 077
mkdir -p "$DEST"
mysqldump --single-transaction --routines CAT2 | gzip -9 > "$DEST/CAT2-$DAY.sql.gz"
tar -czf "$DEST/config-$DAY.tar.gz" -C / etc/nginx etc/isproject etc/fail2ban/jail.d \
    etc/fail2ban/filter.d/trap-honeypot.conf etc/php/8.3/fpm/conf.d/99-hardening.ini 2>/dev/null || true
find "$DEST" -type f -mtime +14 -delete
logger -t isproject-backup "backup written to $DEST ($DAY)"
