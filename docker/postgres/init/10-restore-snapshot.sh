#!/bin/bash
#
# Postgres container'ı İLK KEZ kurulduğunda (veri klasörü boşken) çalışır.
#
# database/backups/ altında bir pg_dump snapshot'ı varsa onu geri yükler —
# böylece Docker'da da demo ile birebir aynı veriyle çalışılır. Snapshot yoksa
# (örn. repo taze klonlandıysa; yedekler .gitignore'da) hiçbir şey yapmaz:
# şema migration ile kurulur, seeder sentetik veri üretir.
#
# DİKKAT: Burada bilerek `exit` KULLANILMIYOR. Postgres'in resmi entrypoint'i
# bu klasördeki .sh dosyalarını, executable değillerse `source` ile çalıştırır;
# o durumda bir `exit` çağrısı tüm kurulum sürecini yarıda keserdi.
#
set -u

SNAPSHOT_DIR="/snapshots"
DUMP=""

if [ -d "$SNAPSHOT_DIR" ]; then
    DUMP="$(ls -1t "$SNAPSHOT_DIR"/*.dump 2>/dev/null | head -1)"
fi

if [ -z "${DUMP}" ]; then
    echo "[postgres-init] Snapshot bulunamadı — şema migration ile kurulacak."
else
    echo "[postgres-init] Snapshot geri yükleniyor: $DUMP"

    # --no-owner / --no-privileges: dump host'taki 'tunahansari' rolüne aitti;
    # o rol bu container'da yok. Bu bayraklar sahipliği bağlanan kullanıcıya
    # devrediyor, aksi halde her tabloda "role does not exist" hatası alırdık.
    if pg_restore \
        --no-owner \
        --no-privileges \
        --username "$POSTGRES_USER" \
        --dbname "$POSTGRES_DB" \
        "$DUMP"
    then
        echo "[postgres-init] Snapshot başarıyla geri yüklendi."
    else
        echo "[postgres-init] pg_restore uyarılarla tamamlandı (yoksayıldı)."
    fi
fi
