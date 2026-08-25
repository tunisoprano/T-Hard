"""
Kullanıcı-ürün satın alma geçmişinden collaborative filtering (ALS) ile
öneri modeli eğitir ve sonucu Postgres'teki `recommendations` tablosuna yazar.

Çalıştırma: python train.py
"""

import os

import numpy as np
import psycopg2
from dotenv import load_dotenv
from implicit.als import AlternatingLeastSquares
from scipy.sparse import csr_matrix

load_dotenv()

DB_CONFIG = {
    "host": os.getenv("DB_HOST"),
    "port": os.getenv("DB_PORT"),
    "dbname": os.getenv("DB_NAME"),
    "user": os.getenv("DB_USER"),
    "password": os.getenv("DB_PASSWORD"),
}

TOP_N = 10
FACTORS = 20
ITERATIONS = 20
REGULARIZATION = 0.1
ALPHA = 15
CATEGORY_AFFINITY_WEIGHT = 1.0


def fetch_interactions(conn):
    """
    Her (kullanıcı, ürün) çifti için toplam satın alınan adedi çeker.
    Bu adet, ALS'nin "confidence" (güven) skoru olarak kullanılacak —
    bir ürünü 5 kere alan kullanıcı, 1 kere alana göre o ürüne çok
    daha güçlü bir ilgi sinyali vermiş demektir.
    """
    query = """
        SELECT o.user_id, oi.product_id, SUM(oi.quantity) AS total_qty
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        GROUP BY o.user_id, oi.product_id
    """
    with conn.cursor() as cur:
        cur.execute(query)
        return cur.fetchall()


def fetch_product_categories(conn):
    """product_id -> category_id eşlemesi, kategori bazlı hibrit skorlama için."""
    with conn.cursor() as cur:
        cur.execute("SELECT id, category_id FROM products")
        return dict(cur.fetchall())


def fetch_product_stores(conn):
    """
    product_id -> store_id eşlemesi. Her öneriyi HANGİ mağaza için
    ürettiğimizi bilmemiz lazım — bir mağazanın asistanı sadece kendi
    ürününü pazarlamalı, başka mağazanın ürününü öneremez.
    """
    with conn.cursor() as cur:
        cur.execute("SELECT id, store_id FROM products")
        return dict(cur.fetchall())


def compute_category_affinity(interactions, product_categories):
    """
    Her kullanıcının, satın aldığı toplam miktarın yüzde kaçının hangi
    kategoriye gittiğini hesaplar. Örn. bir kullanıcı 10 ürün almış,
    7'si spor ise affinity[user][spor_category_id] = 0.7 olur.

    Bu, ALS'nin tek başına yakalayamadığı "bu kullanıcı ağırlıklı olarak
    şu kategoriden alışveriş yapıyor" sinyalini açıkça modele ekler.
    """
    user_category_totals = {}
    user_totals = {}

    for user_id, product_id, qty in interactions:
        category_id = product_categories.get(product_id)
        if category_id is None:
            continue
        qty = float(qty)
        user_category_totals.setdefault(user_id, {})
        user_category_totals[user_id][category_id] = user_category_totals[user_id].get(category_id, 0.0) + qty
        user_totals[user_id] = user_totals.get(user_id, 0.0) + qty

    affinity = {}
    for user_id, category_totals in user_category_totals.items():
        total = user_totals[user_id]
        affinity[user_id] = {
            category_id: qty / total for category_id, qty in category_totals.items()
        }

    return affinity


def build_matrix(interactions):
    """
    (user_id, product_id, qty) satırlarını, ALS'nin beklediği
    seyrek (sparse) kullanıcı x ürün matrisine çevirir.

    Veritabanındaki ID'ler ardışık/sıfırdan başlamıyor olabilir
    (silinen kayıtlar vs.), bu yüzden gerçek ID'leri 0'dan başlayan
    matris indekslerine eşleyen sözlükler tutuyoruz.
    """
    user_ids = sorted({row[0] for row in interactions})
    product_ids = sorted({row[1] for row in interactions})

    user_index = {uid: idx for idx, uid in enumerate(user_ids)}
    product_index = {pid: idx for idx, pid in enumerate(product_ids)}

    # ALS "confidence" (güven) skoru bekler, ham adet değil. Standart formül:
    # confidence = 1 + alpha * adet. Böylece 3 kere alınan bir ürün, 1 kere
    # alınana göre çok daha güçlü bir sinyal olarak modele girer.
    rows = [user_index[u] for u, p, qty in interactions]
    cols = [product_index[p] for u, p, qty in interactions]
    values = [1.0 + ALPHA * float(qty) for u, p, qty in interactions]

    matrix = csr_matrix(
        (values, (rows, cols)),
        shape=(len(user_ids), len(product_ids)),
    )

    return matrix, user_ids, product_ids


def train_model(user_items):
    model = AlternatingLeastSquares(
        factors=FACTORS,
        regularization=REGULARIZATION,
        iterations=ITERATIONS,
    )
    model.fit(user_items)
    return model


def generate_recommendations(model, user_items, user_ids, product_ids, product_categories, product_stores, category_affinity):
    """
    Her kullanıcı için henüz almadığı TÜM ürünleri aday olarak alır
    (saf ALS skoruyla), kategori tercihine göre yeniden ağırlıklandırır,
    SONRA adayları mağazaya göre gruplayıp HER MAĞAZA İÇİN AYRI bir
    TOP_N listesi üretir.

    Neden mağaza bazında ayrı liste? Bir mağazanın asistanı sadece kendi
    ürününü pazarlamalı — "genel olarak en iyi 10 ürün" listesi çoğu
    zaman tek bir mağazaya ait olmaz. Mağaza bazlı ayırınca, kullanıcının
    o mağazadaki alışverişe hiç girmemiş olsa bile, o mağazanın
    kataloğundan kendisine en uygun 10 ürünü görebiliyoruz.

    final_score = als_score + CATEGORY_AFFINITY_WEIGHT * kategori_payi

    Toplamsal (additive) bir formül kullanıyoruz, çarpımsal değil.
    Çünkü ALS skorları negatif de olabiliyor (matematiksel olarak bir
    "benzerlik" ölçüsü, sınırlı bir aralıkta değil) — negatif bir skoru
    pozitif bir çarpanla çarpmak onu daha da küçültür, tam tersi etki
    yapar. Toplama işlemi bu sorunu yaşamıyor: düşük/negatif ALS skorlu
    ama kullanıcının sevdiği kategorideki bir ürün, sabit bir bonus
    alıp yüksek skorlu ama alakasız kategorideki ürünlerle yarışabiliyor.
    filter_already_liked_items=True, zaten satın alınmış ürünleri
    (dolayısıyla kendi mağazasındaki aynı ürünleri) baştan eliyor.
    """
    ids, scores = model.recommend(
        userid=np.arange(len(user_ids)),
        user_items=user_items,
        N=len(product_ids),
        filter_already_liked_items=True,
    )

    results = []

    for row_idx, user_id in enumerate(user_ids):
        affinity = category_affinity.get(user_id, {})

        candidates_by_store = {}
        for product_row, als_score in zip(ids[row_idx], scores[row_idx]):
            if product_row < 0:
                continue
            product_id = product_ids[product_row]
            category_id = product_categories.get(product_id)
            store_id = product_stores.get(product_id)
            category_share = affinity.get(category_id, 0.0)
            final_score = float(als_score) + CATEGORY_AFFINITY_WEIGHT * category_share
            candidates_by_store.setdefault(store_id, []).append((product_id, final_score))

        for store_id, candidates in candidates_by_store.items():
            candidates.sort(key=lambda c: c[1], reverse=True)
            for rank, (product_id, final_score) in enumerate(candidates[:TOP_N], start=1):
                results.append((user_id, store_id, product_id, final_score, rank))

    return results


def save_recommendations(conn, recommendations):
    with conn.cursor() as cur:
        cur.execute("DELETE FROM recommendations")
        cur.executemany(
            """
            INSERT INTO recommendations (user_id, store_id, product_id, score, rank, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, NOW(), NOW())
            """,
            recommendations,
        )
    conn.commit()


def main():
    conn = psycopg2.connect(**DB_CONFIG)

    print("Satın alma verisi çekiliyor...")
    interactions = fetch_interactions(conn)
    print(f"{len(interactions)} kullanıcı-ürün etkileşimi bulundu.")

    product_categories = fetch_product_categories(conn)
    product_stores = fetch_product_stores(conn)
    category_affinity = compute_category_affinity(interactions, product_categories)

    user_items, user_ids, product_ids = build_matrix(interactions)
    print(f"Matris boyutu: {len(user_ids)} kullanıcı x {len(product_ids)} ürün")

    print("ALS modeli eğitiliyor...")
    model = train_model(user_items)

    print(f"Her kullanıcı + her mağaza için en iyi {TOP_N} öneri hesaplanıyor (hibrit skor)...")
    recommendations = generate_recommendations(
        model, user_items, user_ids, product_ids, product_categories, product_stores, category_affinity
    )

    print(f"{len(recommendations)} öneri veritabanına yazılıyor...")
    save_recommendations(conn, recommendations)

    conn.close()
    print("Tamamlandı.")


if __name__ == "__main__":
    main()
