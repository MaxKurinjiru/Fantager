#!/bin/sh
set -eu
APP_DIR=/var/www/html

mkdir -p "$APP_DIR/var"
mkdir -p "$APP_DIR/var/cache" "$APP_DIR/var/log" "$APP_DIR/var/sessions" "$APP_DIR/var/share"

# Symfony uses /tmp/fantager for profiler and other runtime storage
mkdir -p /tmp/fantager/cache/dev/profiler
chmod -R 777 /tmp/fantager

# Avoid changing owner of the entire host-mounted directory to prevent resetting host file permissions
# chown -R apache:apache "$APP_DIR" || true
chown -R apache:apache "$APP_DIR/var" || true
chmod -R 0777 "$APP_DIR/var" || true
mkdir -p /var/www/opcache && chown -R apache:apache /var/www/opcache || true

# Apply entity schema on production start (empty DB creates tables; existing DB gets mapping diffs).
if [ "${APP_ENV:-dev}" = "prod" ]; then
    echo "Updating database schema from entities..."
    su -s /bin/sh -c "php bin/console doctrine:schema:update --force --no-interaction" apache
    su -s /bin/sh -c "php bin/console messenger:setup-transports --no-interaction" apache
fi

exec httpd -D FOREGROUND
