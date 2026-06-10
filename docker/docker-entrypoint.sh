#!/bin/sh
set -eu
APP_DIR=/var/www/html

mkdir -p "$APP_DIR/var"
mkdir -p "$APP_DIR/var/cache" "$APP_DIR/var/log" "$APP_DIR/var/sessions"

# Symfony uses /tmp/fantager for profiler and other runtime storage
mkdir -p /tmp/fantager/cache/dev/profiler
chmod -R 777 /tmp/fantager

chown -R apache:apache "$APP_DIR" || true
chown -R apache:apache "$APP_DIR/var" || true
chmod -R 0777 "$APP_DIR/var" || true
mkdir -p /var/www/opcache && chown -R apache:apache /var/www/opcache || true

exec httpd -D FOREGROUND
