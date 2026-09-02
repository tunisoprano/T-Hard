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
| Semantik arama | Ollama, bge-m3 embedding (1024 boyut, çok dilli) |
| Frontend | Saf HTML/CSS/vanilla JS (build adımı yok) |
| Test | PHPUnit (49 test, SQLite in-memory) |

## Docker ile kurulum (önerilen)

Tek ön koşul: **Docker Desktop**. Postgres, RabbitMQ, Ollama, Python ve PHP'yi
tek tek kurmaya gerek yok — hepsi container olarak geliyor.

```bash
docker compose up -d --build
```

İlk çalıştırmada imaj derlenir ve Ollama modelleri indirilir (~6 GB), bu
yüzden ilk açılış uzun sürer. Model indirmenin bitişini izlemek için:

```bash
docker compose logs -f ollama-init
```

Hazır olunca: **http://localhost:8080/chat** ve **http://localhost:8080/magaza/1**

| Servis | Ne yapar | Adres |
| --- | --- | --- |
| `nginx` | Web sunucusu | http://localhost:8080 |
| `app` | Laravel (PHP-FPM), migration + seed'i o çalıştırır | — |
| `queue-worker` | RabbitMQ kuyruğundaki işleri işler | — |
| `scheduler` | Laravel zamanlayıcı (crontab'a gerek yok) | — |
| `postgres` | Veritabanı | localhost:5433 |
| `rabbitmq` | Kuyruk + yönetim arayüzü (t_hard/secret) | http://localhost:15673 |
| `ollama` | Lokal LLM sunucusu | localhost:11435 |
| `ollama-init` | Modelleri bir kereliğine indirir, sonra kapanır | — |

Host portları bilerek kaydırıldı (5433/5673/15673/11435): bu makinede
Homebrew ile kurulu Postgres/RabbitMQ/Ollama standart portları kullanıyor,
Docker onlarla çakışmasın diye.

**Veri:** `database/backups/` içinde bir `.dump` varsa Postgres ilk kurulumda
onu otomatik geri yükler (demo verisiyle birebir aynı ortam). Yoksa şema
migration ile kurulur ve seeder sentetik veri üretir.

Sık kullanılan komutlar:

```bash
docker compose exec app php artisan test          # testler
docker compose exec app php artisan products:embed # embedding üret (kuyruğa atar)
docker compose exec app php artisan recommender:train --sync  # modeli hemen eğit
docker compose logs -f queue-worker                # worker ne yapıyor
docker compose down                                # durdur (veri kalır)
docker compose down -v                             # durdur + tüm veriyi sil
```

> **Performans notu:** Apple Silicon'da container'lar Metal GPU'ya erişemez,
> yani container içindeki Ollama modeli CPU'da çalışır ve host'a göre belirgin
> şekilde yavaştır. Sunum/demo sırasında hız önemliyse, host'ta çalışan
> Ollama'ya bağlanmak için `docker-compose.yml`'deki `OLLAMA_URL` değerini
> `http://host.docker.internal:11434` yapmak yeterli.

## Kurulum (Docker'sız, doğrudan makinede)

Ön koşullar: PHP 8.4+, Composer, Node.js, PostgreSQL 16, Python 3.12,
RabbitMQ, [Ollama](https://ollama.com).

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
pip install -r requirements.txt              # çalışma zamanı paketleri
# pip install -r requirements-notebook.txt  # train.ipynb'yi de açacaksan
OPENBLAS_NUM_THREADS=1 python train.py
cd ..

# Ollama modelleri
brew install ollama
brew services start ollama
ollama pull qwen2.5:7b-instruct
ollama pull bge-m3

# Ürünler için semantik arama embedding'lerini üret
php artisan products:embed

# Siteyi bağla (Laravel Herd kullanıyorsan)
herd link
```

## Kullanım

- `http://<proje-adresi>/chat` — platform geneli, kişisel öneri chatbot'u
  + tüm mağazalarda semantik ürün arama
- `http://<proje-adresi>/magaza/{1-4}` — mağaza vitrini + mağaza-özel
  chatbot + seçili kullanıcının tüm mağazalardaki sipariş geçmişi + o
  mağazaya özel semantik arama

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
- Semantik arama, doğrudan/yakın kelime eşleşmelerinde (örn. "mont",
  "spor ayakkabısı") çok güçlü; soyut/çok adımlı kavramsal sorgularda
  (örn. "yazlık hafif kıyafet") tutarsız sonuçlar verebiliyor — küçük
  yerel embedding modellerinin bilinen bir sınırı

## Sırada ne var

- Öneri açıklanabilirliği (explainability)
- Ürün açıklamalarının (`description`) gerçekçi Türkçe metinle
  değiştirilmesi (şu an Faker'ın anlamsız yer tutucu metni)
