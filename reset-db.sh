#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DB_NAME="auction_db"

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
  mysql -u root -e "DROP DATABASE IF EXISTS ${DB_NAME};"
  mysql -u root -e "CREATE DATABASE ${DB_NAME};"
  mysql -u root "$DB_NAME" < "$APP_DIR/schema.sql"
  mysql -u root "$DB_NAME" < "$APP_DIR/seed_data.sql"
  echo "✅ Database reset complete with fresh sample data."
}

start_mariadb
confirm_reset
reset_database
