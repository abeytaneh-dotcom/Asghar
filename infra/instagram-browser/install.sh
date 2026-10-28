#!/usr/bin/env bash
set -euo pipefail

DOMAIN="${DOMAIN:-instagram.websaza.ir}"
ACCESS_USER="${ACCESS_USER:-websaza}"
ACCESS_PASSWORD="${ACCESS_PASSWORD:-$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9@#%+=_' | head -c 24)}"
INSTALL_DIR="/opt/websaza-instagram"
REPO_RAW_BASE="https://raw.githubusercontent.com/abeytaneh-dotcom/Asghar/websaza-hosting/infra/instagram-browser"

if [ "$(id -u)" -ne 0 ]; then
  echo "Run as root." >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl gnupg openssl
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
. /etc/os-release
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" > /etc/apt/sources.list.d/docker.list
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
systemctl enable --now docker

mkdir -p "$INSTALL_DIR/data/chromium" "$INSTALL_DIR/data/caddy-data" "$INSTALL_DIR/data/caddy-config"
chown -R 1000:1000 "$INSTALL_DIR/data/chromium"

curl -fsSL "$REPO_RAW_BASE/docker-compose.yml" -o "$INSTALL_DIR/docker-compose.yml"

HASH=$(docker run --rm caddy:2-alpine caddy hash-password --plaintext "$ACCESS_PASSWORD")
cat > "$INSTALL_DIR/Caddyfile" <<EOF
$DOMAIN {
    encode zstd gzip
    basic_auth {
        $ACCESS_USER $HASH
    }
    reverse_proxy chromium:3000
}
EOF
chmod 600 "$INSTALL_DIR/Caddyfile"

cd "$INSTALL_DIR"
docker compose pull
docker compose up -d

cat <<EOF

WebSaza Instagram browser stack installed.
Domain: https://$DOMAIN
Access user: $ACCESS_USER
Access password: $ACCESS_PASSWORD

IMPORTANT:
1. Point the DNS A record for $DOMAIN to this VPS IPv4 address.
2. Keep ports 80 and 443 open.
3. Enter Instagram credentials only inside the streamed Chromium window.
4. The Chromium profile is persisted under $INSTALL_DIR/data/chromium.
EOF
