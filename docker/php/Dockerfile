# syntax=docker/dockerfile:1

# ============================================================================
# T-Hard uygulama imajı — PHP (Laravel) + Python (ALS öneri motoru)
#
# Neden ikisi TEK imajda?
# `recommender:train` komutu, Laravel içinden Symfony Process ile doğrudan
# `python train.py` çağırıyor (bkz. TrainRecommenderJob). Python'ı ayrı bir
# container'a taşımak, araya bir RPC/kuyruk katmanı koymayı gerektirirdi —
# bu ölçekte gereksiz karmaşıklık olurdu.
#
# Neden bookworm (Debian 12)?
# Bookworm, Python 3.11 getiriyor. `implicit==0.7.2` paketinin ARM64 Linux
# (aarch64) wheel'leri cp311'e kadar yayınlanmış — yani 3.11'de hazır wheel
# iniyor, kaynaktan derleme/emülasyon gerekmiyor. Daha yeni bir Python'da
# (3.12+) wheel bulunamaz ve build ya çok uzar ya da patlar.
# ============================================================================
FROM php:8.4-fpm-bookworm

# --- Sistem paketleri -------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        postgresql-client \
        python3 \
        python3-venv \
        python3-dev \
    && rm -rf /var/lib/apt/lists/*

# --- PHP eklentileri --------------------------------------------------------
# pdo_pgsql/pgsql : PostgreSQL bağlantısı
# pcntl           : queue:work'ün sinyalleri yakalayıp düzgün kapanabilmesi
# sockets, bcmath : php-amqplib (RabbitMQ sürücüsü) bunları istiyor
RUN docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        pcntl \
        sockets \
        bcmath \
        intl \
        zip \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- PHP / FPM ayarları -----------------------------------------------------
# zz-app.conf içindeki `clear_env = no` KRİTİK: onsuz php-fpm, container'a
# verilen ortam değişkenlerini PHP'den gizler ve Laravel hiçbir servise
# (Postgres/RabbitMQ/Ollama) bağlanamaz.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/html

# --- Python sanal ortamı ----------------------------------------------------
# DİKKAT: venv proje klasörüne DEĞİL, /opt/venv'e kuruluyor. Sebebi:
# geliştirmede proje klasörü host'tan bind-mount ediliyor; host'taki macOS
# venv'i container'ınkini gölgeler ve "wrong architecture" hatası alırdık.
# Uygulama bu yolu RECOMMENDER_PYTHON ortam değişkeninden okuyor.
#
# Sadece requirements.txt (çalışma zamanı) kuruluyor; jupyter/pandas/matplotlib
# gibi yalnızca train.ipynb'nin ihtiyaç duyduğu paketler
# requirements-notebook.txt'te ayrı duruyor ve imaja girmiyor.
COPY recommender/requirements.txt /tmp/requirements.txt
RUN python3 -m venv /opt/venv \
    && /opt/venv/bin/pip install --no-cache-dir --retries 10 --timeout 60 --upgrade pip \
    && /opt/venv/bin/pip install --no-cache-dir --retries 10 --timeout 60 -r /tmp/requirements.txt \
    && rm /tmp/requirements.txt \
    && /opt/venv/bin/python -c "import implicit, psycopg2, numpy, scipy, dotenv; print('python bagimliliklari OK')"

# --- PHP bağımlılıkları -----------------------------------------------------
# Önce sadece composer dosyaları kopyalanıyor ki, kod değiştiğinde bu katman
# cache'ten gelsin (her build'de yeniden indirilmesin).
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

# --- Uygulama kodu ----------------------------------------------------------
COPY . .
RUN composer dump-autoload --optimize --no-scripts

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
