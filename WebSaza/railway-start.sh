#!/bin/sh
set -eu

PORT_VALUE="${PORT:-8080}"
PERSIST_ROOT="${NIAZESTAN_DATA_DIR:-/data}"
DB_DIR="$PERSIST_ROOT/marketplace-data"
MEDIA_DIR="$PERSIST_ROOT/marketplace-uploads"

sed -ri "s/^Listen [0-9]+/Listen ${PORT_VALUE}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT_VALUE}>/" /etc/apache2/sites-available/000-default.conf

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
