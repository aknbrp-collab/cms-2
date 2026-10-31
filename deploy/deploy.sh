#!/usr/bin/env bash
#
# Zero-downtime deploy on the VPS (see docs/deployment.md).
#
#   /var/www/cms/
#     releases/20261101120000/   one checkout per deploy
#     shared/.env                kept across releases
#     shared/storage/            uploads, logs, local backups
#     current -> releases/...    what nginx, cron and Horizon use
#
# Usage (as the deploy user, e.g. www-data):  bash deploy.sh [branch]

set -euo pipefail

APP_DIR=/var/www/cms
REPO=${REPO:-git@github.com:your-org/cms-ready.git}
BRANCH=${1:-main}
KEEP_RELEASES=5

RELEASE="$APP_DIR/releases/$(date +%Y%m%d%H%M%S)"

echo "→ Cloning $BRANCH into $RELEASE"
git clone --depth 1 --branch "$BRANCH" "$REPO" "$RELEASE"
cd "$RELEASE"

echo "→ Linking shared .env and storage"
rm -rf storage
ln -s "$APP_DIR/shared/storage" storage
ln -s "$APP_DIR/shared/.env" .env

echo "→ Installing dependencies and building assets"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
npm ci --no-audit --no-fund
npm run build
rm -rf node_modules

echo "→ Migrating and syncing roles/permissions"
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force

echo "→ Caching config, routes, views and events"
php artisan storage:link
php artisan optimize

echo "→ Checking production readiness"
php artisan cms:check

echo "→ Switching current release"
ln -sfn "$RELEASE" "$APP_DIR/current.new"
mv -Tf "$APP_DIR/current.new" "$APP_DIR/current"

echo "→ Reloading PHP-FPM and restarting Horizon"
sudo systemctl reload php8.4-fpm
php "$APP_DIR/current/artisan" horizon:terminate

echo "→ Removing old releases"
ls -1dt "$APP_DIR"/releases/* | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf

echo "✓ Deployed $(git -C "$RELEASE" rev-parse --short HEAD)"
