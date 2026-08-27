# T-Hard — Mağazalar Arası Ürün Öneri Sistemi

Çok mağazalı (multi-tenant) bir e-ticaret platformu için, kullanıcının geçmiş
satın alma davranışına göre **farklı mağazalardan** ürün öneren bir yapay
zeka sistemi. Örnek senaryo: bir kullanıcı Mağaza A'dan pantolon aldıysa,
Mağaza B'nin pantolonları da ona önerilebilsin.

Bu proje bir staj görevi kapsamında geliştirilmiş bir **prototiptir**.
Kullanılan tüm veri (kullanıcı, sipariş, ürün) sentetiktir — gerçek müşteri
verisi içermez.

## Nasıl çalışıyor

```
┌─────────────┐      ┌──────────────────┐      ┌─────────────────────┐
│  order_items │ ──▶  │  Python (implicit) │ ──▶ │  recommendations tbl │
│  (Postgres)  │      │  ALS + kategori    │      │  (user, store, ürün, │
│              │      │  hibrit skorlama   │      │   skor, sıra)        │
└─────────────┘      └──────────────────┘      └─────────────────────┘
                                                          │
                                                          ▼
┌─────────────┐      ┌──────────────────┐      ┌─────────────────────┐
│   Web UI     │ ◀──  │   Laravel API     │ ◀──  │  Ollama (qwen2.5)    │
│ (chat/mağaza)│      │  (REST + chatbot) │      │  lokal LLM           │
└─────────────┘      └──────────────────┘      └─────────────────────┘
```

1. **Veri modeli** (Laravel + PostgreSQL): mağazalar, kategoriler, her
   mağazaya ait ürünler, kullanıcılar (bir "ana mağazaları" var ama diğer
   mağazalardan da alışveriş yapabiliyorlar) ve siparişler.
2. **Öneri motoru** (`recommender/`, Python): `implicit` kütüphanesiyle
   collaborative filtering (ALS) modeli eğitilir. Kullanıcının geçmiş
   kategori tercihi de skora eklenerek hibrit bir sıralama üretilir, sonuç
   her (kullanıcı, mağaza) çifti için ayrı ayrı `recommendations`
   tablosuna yazılır.
3. **API** (`routes/api.php`): kategori/mağaza/ürün/öneri/sipariş
   endpoint'leri + chatbot endpoint'i.
4. **Chatbot** (Ollama + Qwen2.5, lokal): gerçek veritabanı verisini
   (katalog, öneriler, sipariş geçmişi) prompt'a gömüp modele "sadece bu
   veriyi kullan, uydurma" diyen bir RAG yaklaşımı.
5. **Web arayüzü**: platform geneli chatbot (`/chat`) ve her mağaza için
   ayrı bir vitrin + mağaza-özel chatbot (`/magaza/{id}`).

## Ölçülen sonuçlar

- Kullanıcıların ilk 3 önerisinde doğru kategori isabet oranı: **%89–92**
  (rastgele öneri yapılsaydı bu oran katalog dağılımına göre sadece %15
  olurdu)
- Önerilerin **%71'i** kullanıcının ana mağazası **dışından** geliyor —
  hedeflenen çapraz-mağaza senaryosu çalışıyor
- Detaylı analiz ve grafikler için: `recommender/train.ipynb`

## Teknoloji yığını

| Katman | Teknoloji |
|---|---|
| Backend / API | Laravel 13, PostgreSQL 16 |
| Öneri motoru | Python 3.12, `implicit` (ALS) |
| Chatbot | Ollama, Qwen2.5 7B Instruct (tamamen lokal) |
| Frontend | Saf HTML/CSS/vanilla JS (build adımı yok) |
| Test | PHPUnit (26 test, SQLite in-memory) |

## Kurulum

Ön koşullar: PHP 8.4+, Composer, Node.js, PostgreSQL 16, Python 3.12,
[Ollama](https://ollama.com).

```bash
# Bağımlılıklar
composer install
npm install

# .env dosyalarını hazırla
cp .env.example .env
php artisan key:generate
cp recommender/.env.example recommender/.env   # DB bilgilerini gir

# Veritabanı + sahte veri
php artisan migrate:fresh --seed

# Python ortamı ve öneri motoru
cd recommender
python3.12 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
OPENBLAS_NUM_THREADS=1 python train.py
cd ..

# Ollama modeli
brew install ollama
brew services start ollama
ollama pull qwen2.5:7b-instruct

# Siteyi bağla (Laravel Herd kullanıyorsan)
herd link
```

## Kullanım

- `http://<proje-adresi>/chat` — platform geneli, kişisel öneri chatbot'u
- `http://<proje-adresi>/magaza/{1-4}` — mağaza vitrini + mağaza-özel
  chatbot + seçili kullanıcının tüm mağazalardaki sipariş geçmişi

Kullanıcı girişi (auth) henüz yok; sayfalardaki dropdown'dan "hangi
kullanıcı olarak bakıyorsun" seçiliyor.

## Öneri motorunu yeniden eğitme

Veritabanı her `migrate:fresh --seed` sonrası ürün ID'leri değiştiği için
öneri motorunun yeniden çalıştırılması gerekir:

```bash
php artisan recommender:train
```

Bu komut her gece 03:00'te otomatik olarak da çalışacak şekilde
zamanlanmıştır (`routes/console.php`).

## Test

```bash
php artisan test
```

## Bilinen sınırlamalar

- Kullanılan veri tamamen sentetik/sahte, gerçek müşteri verisiyle henüz
  test edilmedi
- Örneklem küçük (150 kullanıcı)
- Kimlik doğrulama (auth) yok
- Chatbot şu an önerilerini "neden" verdiğini açıklayamıyor
  (explainability) — planlanan bir sonraki adım

## Sırada ne var

- Streaming response (chatbot cevaplarının anlık akması)
- Semantik arama (embedding tabanlı, kavramsal ürün arama)
