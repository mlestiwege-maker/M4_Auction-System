#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DB_NAME="auction_db"
DB_APP_USER="auctionhub"
DB_APP_PASS="auction_password"

ASSUME_YES=false
if [[ "${1:-}" == "--yes" ]]; then
  ASSUME_YES=true
fi

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

confirm_reset() {
  if [[ "$ASSUME_YES" == true ]]; then
    return
  fi

  echo "⚠️  This will DROP and recreate database '$DB_NAME' and reload seed data."
  read -r -p "Type 'yes' to continue: " reply
  if [[ "$reply" != "yes" ]]; then
    echo "ℹ️  Reset cancelled."
    exit 0
  fi
}

reset_database() {
  echo "🧨 Resetting database '$DB_NAME'..."
  
  # Root command with sudo and password
  MYSQL_ROOT="echo '1011011' | sudo -S mysql --skip-ssl -u root"

  $MYSQL_ROOT -e "DROP DATABASE IF EXISTS ${DB_NAME};"
  $MYSQL_ROOT -e "CREATE DATABASE ${DB_NAME};"
  $MYSQL_ROOT -e "CREATE USER IF NOT EXISTS '${DB_APP_USER}'@'localhost' IDENTIFIED BY '${DB_APP_PASS}';"
  $MYSQL_ROOT -e "CREATE USER IF NOT EXISTS '${DB_APP_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_APP_PASS}';"
  $MYSQL_ROOT -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_APP_USER}'@'localhost';"
  $MYSQL_ROOT -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_APP_USER}'@'127.0.0.1';"
  $MYSQL_ROOT -e "FLUSH PRIVILEGES;"
  $MYSQL_ROOT "${DB_NAME}" < "$APP_DIR/schema.sql"
  $MYSQL_ROOT "${DB_NAME}" < "$APP_DIR/seed_data.sql"
  echo "✅ Database reset complete with fresh sample data."
}

start_mariadb
confirm_reset
reset_database
