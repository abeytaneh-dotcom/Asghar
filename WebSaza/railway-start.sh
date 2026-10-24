#!/bin/sh
set -eu

PERSIST_ROOT="${NIAZESTAN_DATA_DIR:-/data}"
DB_DIR="$PERSIST_ROOT/marketplace-data"
MEDIA_DIR="$PERSIST_ROOT/marketplace-uploads"

mkdir -p "$DB_DIR" "$MEDIA_DIR"
chown -R www-data:www-data "$PERSIST_ROOT"
chmod 0750 "$DB_DIR"
chmod 0755 "$MEDIA_DIR"

if [ ! -L /var/www/html/.marketplace-data ]; then
  rm -rf /var/www/html/.marketplace-data
  ln -s "$DB_DIR" /var/www/html/.marketplace-data
fi

if [ ! -L /var/www/html/marketplace-uploads ]; then
  rm -rf /var/www/html/marketplace-uploads
  ln -s "$MEDIA_DIR" /var/www/html/marketplace-uploads
fi

exec apache2-foreground
