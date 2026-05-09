#!/usr/bin/env bash
set -euo pipefail

PIDS="$(pgrep -f 'php -S localhost:8000' || true)"

if [[ -z "$PIDS" ]]; then
  echo "ℹ️  No AuctionHub PHP server process found on localhost:8000"
  exit 0
fi

echo "🛑 Stopping AuctionHub PHP server..."
kill $PIDS

echo "✅ Server stopped."
