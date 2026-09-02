#!/usr/bin/env bash
#
# app / queue-worker / scheduler container'larının ortak açılış adımları.
# Her üçü de aynı imajı kullanıyor, sadece CMD'leri farklı.
#
set -euo pipefail

cd /var/www/html

echo "[entrypoint] Başlatılıyor: $*"

# --- 1. Host'tan sızmış olabilecek cache dosyalarını temizle ----------------
# Proje klasörü host'tan bind-mount ediliyor. Host'ta `config:cache`
# çalıştırılmışsa, bootstrap/cache/config.php içinde DB_HOST=127.0.0.1 gibi
# HOST'a ait değerler donmuş olur ve container'daki ortam değişkenlerini
# ezerdi. Bu yüzden container her açılışta bu cache'leri siliyor.
#
# Sadece ortam değişkenine BAĞLI olan cache'ler siliniyor. packages.php ve
# services.php bilerek dokunulmuyor: içerikleri ortamdan bağımsız (sadece
# paket/provider listesi) ve Laravel bunları yokken kendisi yeniden üretiyor —
# üç container aynı anda açıldığı için aynı dosyaya eşzamanlı yazma riski
# doğardı. Config ve route cache'i ise Laravel kendiliğinden üretmez, o yüzden
# bunları silmek yarış koşulu yaratmıyor.
rm -f bootstrap/cache/config.php \
      bootstrap/cache/routes-*.php

# --- 2. Bağımlılıklar -------------------------------------------------------
# Repo taze klonlanmışsa host'ta vendor/ olmaz; bind-mount o boşluğu
# container'a da taşır. İmajın kendi vendor'ı gölgelendiği için burada
# yeniden kuruyoruz.
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] vendor/ bulunamadı — composer install çalıştırılıyor..."
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts
fi

# --- 3. .env --------------------------------------------------------------
if [ ! -f .env ]; then
    echo "[entrypoint] .env yok — .env.example'dan üretiliyor."
    cp .env.example .env
    php artisan key:generate --force
fi

# --- 4. Yazılabilir klasörler ----------------------------------------------
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/private/user-contexts \
         bootstrap/cache
# Bind-mount'ta host UID'si farklı olabilir; hata verirse görmezden geliyoruz
# (macOS'ta Docker zaten yazma iznini kendi hallediyor).
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

# --- 5. Postgres hazır olana kadar bekle ------------------------------------
# Değişkenler tanımsız olabilir (imaj compose dışında, tek bir komut için
# çalıştırıldığında). O durumda beklemeyi tamamen atlıyoruz — `set -u`
# altında çıplak ${DB_HOST} kullanmak container'ı çökertirdi.
DB_HOST="${DB_HOST:-}"
DB_PORT="${DB_PORT:-5432}"
DB_USERNAME="${DB_USERNAME:-}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_DATABASE="${DB_DATABASE:-}"

if [ -n "$DB_HOST" ]; then
    echo "[entrypoint] Postgres bekleniyor (${DB_HOST}:${DB_PORT})..."
    until pg_isready -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" >/dev/null 2>&1; do
        sleep 1
    done
    echo "[entrypoint] Postgres hazır."
else
    echo "[entrypoint] DB_HOST tanımsız — veritabanı beklemesi atlandı."
fi

# --- 6. Migration + demo verisi (SADECE app container'ında) -----------------
# Üç container da aynı anda migrate çalıştırırsa birbirine girer; bu yüzden
# sadece `app` servisine RUN_MIGRATIONS=true veriliyor.
if [ "${RUN_MIGRATIONS:-false}" = "true" ] && [ -n "$DB_HOST" ]; then
    echo "[entrypoint] Migration'lar çalıştırılıyor..."
    php artisan migrate --force

    # Veritabanı bomboşsa (ne snapshot geri yüklenmiş ne seed atılmış)
    # demo verisini üret. Doluysa hiçbir şeye dokunma.
    STORE_COUNT="$(PGPASSWORD="${DB_PASSWORD}" psql -h "${DB_HOST}" -p "${DB_PORT}" \
        -U "${DB_USERNAME}" -d "${DB_DATABASE}" -tAc 'SELECT COUNT(*) FROM stores' 2>/dev/null || echo 0)"

    if [ "${STORE_COUNT:-0}" = "0" ]; then
        echo "[entrypoint] Veritabanı boş — demo verisi (seed) üretiliyor..."
        php artisan db:seed --force
    else
        echo "[entrypoint] Veritabanında zaten ${STORE_COUNT} mağaza var, seed atlanıyor."
    fi
fi

exec "$@"
