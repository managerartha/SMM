#!/usr/bin/env bash
# Menyusun dist/smm-hostinger.zip untuk Hostinger (website PHP).
# Seluruh isi ZIP diekstrak LANGSUNG ke public_html (seperti LMS). Semua permintaan web masuk lewat
# index.php (.htaccess), sehingga .env, storage, vendor, dll. tidak bisa dibuka dari browser.
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)
OUT="$ROOT/dist"
APP=$(mktemp -d)
VERSION="$(date +%Y.%m.%d)-$(git rev-parse --short HEAD 2>/dev/null || echo lokal)"

echo "→ Build aset (Tailwind/Alpine)"
npm run build >/dev/null

echo "→ Menyalin kode"
rsync -a ./ "$APP/" \
  --exclude .git --exclude node_modules --exclude vendor --exclude dist --exclude tests --exclude tools \
  --exclude .env --exclude '.env.*' --exclude 'storage/app/private/*' --exclude 'storage/app/drive-local' \
  --exclude 'storage/app/google' --exclude 'storage/app/installed.lock' --exclude 'storage/logs/*.log' \
  --exclude 'storage/framework/cache/data/*' --exclude 'storage/framework/sessions/*' --exclude 'storage/framework/views/*' \
  --exclude 'storage/framework/testing' --exclude 'bootstrap/cache/*.php' --exclude phpunit.xml --exclude '.phpunit*' \
  --exclude package.json --exclude package-lock.json --exclude vite.config.js --exclude resources/css --exclude resources/js \
  --exclude .editorconfig --exclude .gitattributes --exclude .gitignore
mkdir -p "$APP/storage/app/private" "$APP/storage/framework/"{cache/data,sessions,views} "$APP/storage/logs" "$APP/bootstrap/cache"
echo "$VERSION" > "$APP/VERSION"

# Pintu masuk & aturan web untuk susunan satu folder
cp tools/hostinger/index.php "$APP/index.php"
cp tools/hostinger/htaccess "$APP/.htaccess"
rm -f "$APP/public/.htaccess"

echo "→ Composer (tanpa paket pengembangan)"
cp composer.json composer.lock "$APP/"
(cd "$APP" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts --quiet)
find "$APP/vendor" -type d \( -name .git -o -name tests -o -name Tests -o -name test -o -name docs -o -name .github \) -prune -exec rm -rf {} +
find "$APP/vendor" -type f \( -name '*.md' -o -name 'phpunit.xml*' -o -name '.gitattributes' -o -name '.gitignore' \) -delete
(cd "$APP" && php artisan package:discover --ansi >/dev/null)
rm -f "$APP/bootstrap/cache/config.php" "$APP/bootstrap/cache/routes-"*.php

# Contoh .env produksi. index.php menyalinnya menjadi .env saat pertama dibuka; installer mengisi sisanya.
# Pembaruan versi tidak menimpa .env yang sudah ada.
sed -e 's/^APP_ENV=local/APP_ENV=production/' -e 's/^APP_DEBUG=true/APP_DEBUG=false/' \
    -e 's#^APP_URL=.*#APP_URL=#' -e 's/^APP_KEY=.*/APP_KEY=/' \
    -e 's/^LOG_LEVEL=debug/LOG_LEVEL=warning/' -e 's/^SMM_DRIVE_DRIVER=local/SMM_DRIVE_DRIVER=google/' \
    -e 's/^DB_HOST=127.0.0.1/DB_HOST=localhost/' -e 's/^DB_DATABASE=smm/DB_DATABASE=/' -e 's/^DB_USERNAME=smm/DB_USERNAME=/' \
    .env.example > "$APP/.env.contoh"

echo "→ Membuat ZIP"
mkdir -p "$OUT"
rm -f "$OUT/smm-hostinger.zip"
(cd "$APP" && zip -qr -X "$OUT/smm-hostinger.zip" .)
rm -rf "$APP"
echo "Selesai: dist/smm-hostinger.zip (versi $VERSION, $(du -h "$OUT/smm-hostinger.zip" | cut -f1))"
