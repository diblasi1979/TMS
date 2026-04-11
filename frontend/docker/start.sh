#!/bin/sh
set -eu

cd /app

if [ ! -d node_modules ] || [ ! -f node_modules/.package-lock.json ]; then
  npm ci
fi

exec npm run dev -- --host 0.0.0.0 --port 5173