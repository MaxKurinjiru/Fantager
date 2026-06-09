#!/bin/sh
set -eu
APP_DIR=/var/www/html

mkdir -p "$APP_DIR/var"
mkdir -p "$APP_DIR/var/cache" "$APP_DIR/var/log" "$APP_DIR/var/sessions"

chown -R apache:apache "$APP_DIR" || true
chown -R apache:apache "$APP_DIR/var" || true
chmod -R 0777 "$APP_DIR/var" || true
mkdir -p /var/www/opcache && chown -R apache:apache /var/www/opcache || true

exec httpd -D FOREGROUND
