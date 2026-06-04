#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$APP_DIR")"
DB_NAME="auction_db"
DB_APP_USER="auctionhub"
DB_APP_PASS="auction_password"
MYSQL_CMD=(sudo mysql --skip-ssl)

LAUNCH_MODE="php"
if [[ "${1:-}" == "--apache" ]]; then
  LAUNCH_MODE="apache"
fi

SEED=false
if [[ "${1:-}" == "--seed" ]]; then
  SEED=true
fi
if [[ "${2:-}" == "--seed" ]]; then
  SEED=true
fi

echo "🔧 Starting AuctionHub setup..."

configure_mysql_admin_command() {
  if "${MYSQL_CMD[@]}" -e "SELECT 1;" >/dev/null 2>&1; then
    return
  fi

  MYSQL_CMD=(sudo mysql)
  if "${MYSQL_CMD[@]}" -e "SELECT 1;" >/dev/null 2>&1; then
    echo "ℹ️  Using sudo mysql (socket auth) for admin database operations."
    return
  fi

  echo "❌ Unable to access MySQL admin account using 'mysql -u root' or 'sudo mysql'."
  echo "   Please verify MariaDB is running and your account has admin privileges."
  exit 1
}

start_mariadb() {
  if command -v systemctl >/dev/null 2>&1; then
    if systemctl is-active --quiet mariadb; then
      echo "✅ MariaDB is already running."
      return
    fi
  fi

  echo "▶️  Starting MariaDB service..."
  if sudo service mariadb start >/dev/null 2>&1 || service mariadb start >/dev/null 2>&1; then
    echo "✅ MariaDB started."
  else
    echo "❌ Could not start MariaDB automatically."
    echo "   Please run: sudo service mariadb start"
    exit 1
  fi
}

prepare_database() {
  echo "🗄️  Preparing database '$DB_NAME'..."
  "${MYSQL_CMD[@]}" -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME};"
  "${MYSQL_CMD[@]}" -e "CREATE USER IF NOT EXISTS '${DB_APP_USER}'@'localhost' IDENTIFIED BY '${DB_APP_PASS}';"
  "${MYSQL_CMD[@]}" -e "CREATE USER IF NOT EXISTS '${DB_APP_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_APP_PASS}';"
  "${MYSQL_CMD[@]}" -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_APP_USER}'@'localhost';"
  "${MYSQL_CMD[@]}" -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_APP_USER}'@'127.0.0.1';"
  "${MYSQL_CMD[@]}" -e "FLUSH PRIVILEGES;"
  "${MYSQL_CMD[@]}" "$DB_NAME" < "$APP_DIR/schema.sql"

  if [[ "$SEED" == true ]]; then
    echo "🌱 Loading seed data..."
    "${MYSQL_CMD[@]}" "$DB_NAME" < "$APP_DIR/seed_data.sql"
  fi

  echo "✅ Database ready."
}

start_php_server() {
  echo "🚀 Launching PHP server at http://localhost:8000"
  echo "   Press Ctrl+C to stop."
  cd "$ROOT_DIR"
  php -S localhost:8000
}

start_apache_server() {
  local url="http://localhost/auction_system/index.php"

  if [[ -f "/etc/apache2/sites-available/auctionhub.conf" ]]; then
    sudo a2ensite auctionhub.conf >/dev/null 2>&1 || true
    sudo a2dissite 000-default.conf >/dev/null 2>&1 || true
  fi

  if command -v systemctl >/dev/null 2>&1 && systemctl is-active --quiet apache2; then
    echo "✅ Apache is already running."
  else
    echo "▶️  Starting Apache service..."
    sudo systemctl start apache2
  fi

  sudo systemctl reload apache2 >/dev/null 2>&1 || true

  echo "🚀 Apache is ready at $url"
  if command -v google-chrome >/dev/null 2>&1; then
    google-chrome "$url" >/dev/null 2>&1 &
  elif command -v chromium >/dev/null 2>&1; then
    chromium "$url" >/dev/null 2>&1 &
  elif command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$url" >/dev/null 2>&1 &
  fi

  echo "   Open this URL if it doesn't launch automatically: $url"
}

start_mariadb
configure_mysql_admin_command
prepare_database

if [[ "$LAUNCH_MODE" == "apache" ]]; then
  start_apache_server
else
  start_php_server
fi
