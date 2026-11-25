#!/usr/bin/env bash
# Menyusun dist/smm-hostinger.zip siap diunggah ke Hostinger lewat File Manager.
# Isi ZIP:
#   smm/          → kode aplikasi (DI LUAR public_html, tidak bisa diakses dari web)
#   public_html/  → hanya index.php, .htaccess, aset build
# Ekstrak ZIP di folder domains/mutu.arthasafetyindonesia.com/ (lihat docs/DEPLOY_HOSTINGER.md).
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)
OUT="$ROOT/dist"
WORK=$(mktemp -d)
APP="$WORK/smm"
PUB="$WORK/public_html"
VERSION="$(date +%Y.%m.%d)-$(git rev-parse --short HEAD 2>/dev/null || echo lokal)"

echo "→ Build aset (Tailwind/Alpine)"
npm run build >/dev/null

echo "→ Menyalin kode"
mkdir -p "$APP" "$PUB"
rsync -a ./ "$APP/" \
  --exclude .git --exclude node_modules --exclude vendor --exclude dist --exclude tests --exclude tools \
  --exclude .env --exclude '.env.*' --exclude 'storage/app/private/*' --exclude 'storage/app/drive-local' \
  --exclude 'storage/app/google' --exclude 'storage/app/installed.lock' --exclude 'storage/logs/*.log' \
  --exclude 'storage/framework/cache/data/*' --exclude 'storage/framework/sessions/*' --exclude 'storage/framework/views/*' \
  --exclude 'storage/framework/testing' --exclude 'bootstrap/cache/*.php' --exclude phpunit.xml --exclude '.phpunit*' \
  --exclude package.json --exclude package-lock.json --exclude vite.config.js --exclude resources/css --exclude resources/js
mv "$APP/public/"* "$APP/public/".htaccess "$PUB/"
rmdir "$APP/public"
mkdir -p "$APP/storage/app/private" "$APP/storage/framework/"{cache/data,sessions,views} "$APP/storage/logs" "$APP/bootstrap/cache"
echo "$VERSION" > "$APP/VERSION"

# index.php menunjuk ke folder smm di sebelah public_html
sed -i "s#^\$base = __DIR__.'/..';#\$base = __DIR__.'/../smm';#" "$PUB/index.php"
grep -q "__DIR__.'/../smm'" "$PUB/index.php"

# Lapisan pengaman bila folder smm tidak sengaja berada di dalam folder publik
printf 'Require all denied\n' > "$APP/.htaccess"

echo "→ Composer (tanpa paket pengembangan)"
cp composer.json composer.lock "$APP/"
(cd "$APP" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts --quiet)
# Buang berkas yang tidak dipakai di server (hemat ruang & inode hosting)
find "$APP/vendor" -type d \( -name .git -o -name tests -o -name Tests -o -name test -o -name docs -o -name .github \) -prune -exec rm -rf {} +
find "$APP/vendor" -type f \( -name '*.md' -o -name 'phpunit.xml*' -o -name '.gitattributes' -o -name '.gitignore' \) -delete
(cd "$APP" && php artisan package:discover --ansi >/dev/null)
rm -f "$APP/bootstrap/cache/config.php" "$APP/bootstrap/cache/routes-"*.php

# Contoh .env produksi (TIDAK menimpa .env yang sudah ada saat pembaruan)
TOKEN=$(php -r 'echo bin2hex(random_bytes(12));')
sed -e 's/^APP_ENV=local/APP_ENV=production/' -e 's/^APP_DEBUG=true/APP_DEBUG=false/' \
    -e 's#^APP_URL=.*#APP_URL=https://mutu.arthasafetyindonesia.com#' -e 's/^APP_KEY=.*/APP_KEY=/' \
    -e 's/^LOG_LEVEL=debug/LOG_LEVEL=warning/' -e 's/^SMM_DRIVE_DRIVER=local/SMM_DRIVE_DRIVER=google/' \
    -e 's/^SESSION_SECURE_COOKIE=false/SESSION_SECURE_COOKIE=true/' -e "s/^SMM_INSTALL_TOKEN=/SMM_INSTALL_TOKEN=$TOKEN/" \
    -e 's/^MAIL_MAILER=log/MAIL_MAILER=smtp/' -e 's/^MAIL_HOST=127.0.0.1/MAIL_HOST=smtp.hostinger.com/' \
    -e 's/^MAIL_PORT=2525/MAIL_PORT=465/' -e 's/^MAIL_SCHEME=null/MAIL_SCHEME=smtps/' \
    -e 's/^DB_DATABASE=smm/DB_DATABASE=u000000000_smm/' -e 's/^DB_USERNAME=smm/DB_USERNAME=u000000000_smm/' \
    .env.example > "$APP/.env.contoh"

echo "→ Membuat ZIP"
mkdir -p "$OUT"
rm -f "$OUT/smm-hostinger.zip"
(cd "$WORK" && zip -qr -X "$OUT/smm-hostinger.zip" smm public_html)
rm -rf "$WORK"
echo "Selesai: dist/smm-hostinger.zip (versi $VERSION, $(du -h "$OUT/smm-hostinger.zip" | cut -f1))"
