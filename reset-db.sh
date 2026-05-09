#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DB_NAME="auction_db"
DB_APP_USER="auctionhub"
DB_APP_PASS="auction_password"
MYSQL_CMD=(mysql -u root)

ASSUME_YES=false
if [[ "${1:-}" == "--yes" ]]; then
  ASSUME_YES=true
fi

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
  "${MYSQL_CMD[@]}" -e "DROP DATABASE IF EXISTS ${DB_NAME};"
  "${MYSQL_CMD[@]}" -e "CREATE DATABASE ${DB_NAME};"
  "${MYSQL_CMD[@]}" -e "CREATE USER IF NOT EXISTS '${DB_APP_USER}'@'localhost' IDENTIFIED BY '${DB_APP_PASS}';"
  "${MYSQL_CMD[@]}" -e "CREATE USER IF NOT EXISTS '${DB_APP_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_APP_PASS}';"
  "${MYSQL_CMD[@]}" -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_APP_USER}'@'localhost';"
  "${MYSQL_CMD[@]}" -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_APP_USER}'@'127.0.0.1';"
  "${MYSQL_CMD[@]}" -e "FLUSH PRIVILEGES;"
  "${MYSQL_CMD[@]}" "$DB_NAME" < "$APP_DIR/schema.sql"
  "${MYSQL_CMD[@]}" "$DB_NAME" < "$APP_DIR/seed_data.sql"
  echo "✅ Database reset complete with fresh sample data."
}

start_mariadb
configure_mysql_admin_command
confirm_reset
reset_database
