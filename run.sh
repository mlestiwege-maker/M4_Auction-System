#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$APP_DIR")"
DB_NAME="auction_db"

SEED=false
if [[ "${1:-}" == "--seed" ]]; then
  SEED=true
fi

echo "🔧 Starting AuctionHub setup..."

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
  mysql -u root -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME};"
  mysql -u root "$DB_NAME" < "$APP_DIR/schema.sql"

  if [[ "$SEED" == true ]]; then
    echo "🌱 Loading seed data..."
    mysql -u root "$DB_NAME" < "$APP_DIR/seed_data.sql"
  fi

  echo "✅ Database ready."
}

start_php_server() {
  echo "🚀 Launching PHP server at http://localhost:8000"
  echo "   Press Ctrl+C to stop."
  cd "$ROOT_DIR"
  php -S localhost:8000
}

start_mariadb
prepare_database
start_php_server
