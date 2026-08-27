<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PersonaSeeder extends Seeder
{
    /**
     * Sabit persona'lar için tek kategori eşleşmesi.
     * %80 dağılımda bu kategoriden ürün seçilecek.
     */
    private const PERSONA_CATEGORY_MAP = [
        'spor_sever' => 'spor',
        'teknoloji_meraklisi' => 'teknoloji',
        'ev_yasam' => 'ev-yasam',
    ];

    /**
     * giyim_odakli persona'sı tek bir kategoriye değil, bu havuzdan
     * kullanıcı bazında rastgele seçilen bir "favori ürün tipine" bağlanır.
     * Böylece biri pantolon, biri gömlek sever gibi gerçekçi bir alt-tercih oluşur.
     */
    private const CLOTHING_CATEGORY_SLUGS = [
        'pantolon', 'gomlek', 'tisort', 'elbise', 'ceket-mont', 'ayakkabi', 'canta-aksesuar',
    ];

    private const CATEGORIES = [
        ['name' => 'Pantolon', 'slug' => 'pantolon'],
        ['name' => 'Gömlek', 'slug' => 'gomlek'],
        ['name' => 'Tişört', 'slug' => 'tisort'],
        ['name' => 'Elbise', 'slug' => 'elbise'],
        ['name' => 'Ceket & Mont', 'slug' => 'ceket-mont'],
        ['name' => 'Ayakkabı', 'slug' => 'ayakkabi'],
        ['name' => 'Çanta & Aksesuar', 'slug' => 'canta-aksesuar'],
        ['name' => 'Spor & Outdoor', 'slug' => 'spor'],
        ['name' => 'Teknoloji', 'slug' => 'teknoloji'],
        ['name' => 'Ev & Yaşam', 'slug' => 'ev-yasam'],
        ['name' => 'Kozmetik & Bakım', 'slug' => 'kozmetik-bakim'],
    ];

    /**
     * Şirketin asıl işi giyim olduğu için giyim kategorilerine ürün
     * dağıtımında 2x, diğer kategorilere 1x ağırlık veriyoruz.
     */
    private const CATEGORY_WEIGHT_CLOTHING = 2;

    private const CATEGORY_WEIGHT_OTHER = 1;

    /**
     * Faker'ın "Lorem Ipsum" tarzı anlamsız kelimeleri yerine, kategoriye
     * özel gerçekçi Türkçe ürün isimleri. Aynı isim birden fazla mağazada
     * tekrar edebilir (gerçek hayatta da aynı ürün tipi farklı mağazalarda
     * satılır), bu bir sorun değil.
     */
    private const PRODUCT_NAMES = [
        'pantolon' => ['Slim Fit Kot Pantolon', 'Regular Fit Kumaş Pantolon', 'Jogger Pantolon', 'Kargo Pantolon', 'Yüksek Bel Pantolon', 'Chino Pantolon', 'Bol Paça Pantolon', 'Likralı Tayt Pantolon', 'Kadife Pantolon', 'Keten Pantolon', 'Culotte Pantolon', 'Straight Fit Pantolon', 'Paraşüt Pantolon', 'Kumaş Şort Pantolon', 'Yüksek Bel Kot Pantolon', 'Bilek Boy Pantolon', 'Skinny Pantolon', 'Klasik Kumaş Pantolon', 'Baggy Pantolon', 'Palazzo Pantolon'],
        'gomlek' => ['Oxford Gömlek', 'Klasik Kesim Gömlek', 'Keten Gömlek', 'Kareli Gömlek', 'Slim Fit Gömlek', 'Denim Gömlek', 'Çizgili Gömlek', 'Saten Gömlek', 'Uzun Kollu Gömlek', 'Düz Renk Gömlek', 'Kısa Kollu Gömlek', 'Poplin Gömlek', 'Flanel Gömlek', 'Rahat Kesim Gömlek', 'Beyaz Gömlek', 'Desenli Gömlek', 'Yakasız Gömlek', 'Battal Beden Gömlek', 'Vual Gömlek', 'Crop Gömlek'],
        'tisort' => ['Bisiklet Yaka Tişört', 'V Yaka Tişört', 'Oversize Tişört', 'Baskılı Tişört', 'Basic Tişört', 'Uzun Kollu Tişört', 'Ribana Tişört', 'Crop Tişört', 'Pamuklu Tişört', 'Nakışlı Tişört', 'Polo Yaka Tişört', 'Çizgili Tişört', 'Fitilli Tişört', 'Askılı Tişört', 'Kısa Kollu Tişört', 'Battal Beden Tişört', 'Slim Fit Tişört', 'Yazılı Tişört', 'Yıkamalı Tişört', 'Örme Tişört'],
        'elbise' => ['Midi Elbise', 'Mini Elbise', 'Maxi Elbise', 'Gömlek Elbise', 'Askılı Elbise', 'Triko Elbise', 'Volanlı Elbise', 'Straplez Elbise', 'Çiçek Desenli Elbise', 'Abiye Elbise', 'Kruvaze Elbise', 'Kalem Elbise', 'Şık Ofis Elbise', 'Denim Elbise', 'Büzgülü Elbise', 'Balon Kol Elbise', 'Örme Elbise', 'Yazlık Elbise', 'Uzun Kollu Elbise', 'Desenli Yazlık Elbise'],
        'ceket-mont' => ['Deri Ceket', 'Kaban', 'Parka Mont', 'Bomber Ceket', 'Yağmurluk', 'Blazer Ceket', 'Kapüşonlu Mont', 'Yelek', 'Kruvaze Ceket', 'Şişme Mont', 'Trençkot', 'Kadife Ceket', 'Denim Ceket', 'Yünlü Kaban', 'Rüzgarlık', 'Puffer Mont', 'Oversize Ceket', 'Yelekli Ceket', 'Deri Mont', 'Kayak Montu'],
        'ayakkabi' => ['Spor Ayakkabı', 'Klasik Ayakkabı', 'Bot', 'Sandalet', 'Loafer', 'Topuklu Ayakkabı', 'Babet', 'Sneaker', 'Terlik', 'Çizme', 'Oxford Ayakkabı', 'Platform Ayakkabı', 'Yürüyüş Ayakkabısı', 'Stiletto', 'Espadril', 'Bot Sneaker', 'Deri Ayakkabı', 'Dolgu Topuk', 'Mokasen', 'Bileğe Bağlı Sandalet'],
        'canta-aksesuar' => ['Sırt Çantası', 'Omuz Çantası', 'Cüzdan', 'Kemer', 'Şapka', 'Atkı', 'Eldiven', 'Güneş Gözlüğü', 'El Çantası', 'Bel Çantası', 'Postacı Çantası', 'Sırt Çantası Mini', 'Deri Cüzdan', 'Bere', 'Fular', 'Saat', 'Küpe Seti', 'Kolye', 'Bileklik', 'Çanta Askısı'],
        'spor' => ['Koşu Ayakkabısı', 'Yoga Matı', 'Fitness Eldiveni', 'Spor Çantası', 'Dambıl Seti', 'Spor Bandı', 'Su Şişesi', 'Spor Şort', 'Koşu Taytı', 'Antrenman Eldiveni', 'Direnç Bandı', 'Spor Sütyeni', 'Koşu Montu', 'Kettlebell', 'İp Atlama', 'Spor Havlusu', 'Termos Matara', 'Spor Bel Çantası', 'Kondisyon Bisikleti Eldiveni', 'Pilates Topu'],
        'teknoloji' => ['Kablosuz Kulaklık', 'Akıllı Saat', 'Powerbank', 'Bluetooth Hoparlör', 'Telefon Kılıfı', 'Şarj Kablosu', 'Tablet Kılıfı', 'Kablosuz Klavye', 'Kablosuz Mouse', 'Webcam', 'Kablosuz Şarj Cihazı', 'USB Hub', 'Taşınabilir SSD', 'Ekran Koruyucu', 'Telefon Standı', 'Bluetooth Kulak İçi', 'Akıllı Bileklik', 'Mini Projektör', 'Oyuncu Kulaklığı', 'Kablosuz Mikrofon'],
        'ev-yasam' => ['Nevresim Takımı', 'Dekoratif Yastık', 'Kokulu Mum', 'Halı', 'Duvar Saati', 'Battaniye', 'Masa Örtüsü', 'Fon Perde', 'Dekoratif Vazo', 'Duvar Aynası', 'Yatak Örtüsü', 'Çift Kişilik Nevresim', 'Halı Yolluk', 'Aromaterapi Difüzör', 'Dekoratif Tablo', 'Şamdan', 'Sehpa Örtüsü', 'Klozet Takımı', 'Banyo Paspası', 'Mutfak Önlüğü'],
        'kozmetik-bakim' => ['Nemlendirici Krem', 'Parfüm', 'Şampuan', 'Ruj', 'Cilt Bakım Seti', 'Güneş Kremi', 'Yüz Serumu', 'Bakım Maskesi', 'Deodorant', 'Saç Bakım Yağı', 'Göz Kremi', 'Temizleme Jeli', 'Tonik', 'Saç Kremi', 'El Kremi', 'Vücut Losyonu', 'Makyaj Temizleyici', 'Far Paleti', 'Maskara', 'Dudak Balsamı'],
    ];

    private const STORE_NAMES = [
        'T-Hard Market',
        'T-Hard Plus',
        'T-Hard Prime',
        'T-Hard Bazaar',
    ];

    private const TOTAL_USERS = 150;

    private const TOTAL_PRODUCTS = 400;

    private const TOTAL_ORDERS = 1500;

    /**
     * Bir siparişin kullanıcının kendi ana mağazasına düşme yüzdesi.
     * Kalanı diğer mağazalara dağılır — müşteri sadakati var ama mutlak değil.
     */
    private const HOME_STORE_ORDER_RATE = 65;

    public function run(): void
    {
        DB::transaction(function () {
            $stores = collect(self::STORE_NAMES)
                ->map(fn (string $name) => Store::create(['name' => $name]));

            $categories = collect(self::CATEGORIES)
                ->map(fn (array $category) => Category::create($category))
                ->keyBy('slug');

            $products = $this->seedProducts($categories, $stores);

            $users = $this->seedUsers($stores);

            $this->seedOrders($users, $categories, $products, $stores);
        });
    }

    /**
     * 150 ürünü kategorilere ağırlıklı (giyim > diğer), her kategori
     * içinde de 4 mağazaya dengeli dağıtır — her ürün artık tek bir
     * mağazanın envanterine ait.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function seedProducts($categories, $stores)
    {
        $weights = $categories->mapWithKeys(fn (Category $category) => [
            $category->slug => in_array($category->slug, self::CLOTHING_CATEGORY_SLUGS, true)
                ? self::CATEGORY_WEIGHT_CLOTHING
                : self::CATEGORY_WEIGHT_OTHER,
        ]);
        $totalWeight = $weights->sum();

        $products = collect();
        $assigned = 0;
        $categoryList = $categories->values();

        foreach ($categoryList as $index => $category) {
            $isLast = $index === $categoryList->count() - 1;
            $count = $isLast
                ? self::TOTAL_PRODUCTS - $assigned
                : (int) round(self::TOTAL_PRODUCTS * $weights[$category->slug] / $totalWeight);
            $assigned += $count;

            $products = $products->merge($this->seedProductsForCategory($category, $stores, $count));
        }

        return $products;
    }

    /**
     * Bir kategori için üretilecek $count ürünü 4 mağazaya olabildiğince
     * eşit dağıtır, böylece her mağaza-kategori kombinasyonunda en az bir
     * ürün olur (öneri motoru "aynı kategoriden başka mağaza" arayabilsin).
     */
    private function seedProductsForCategory(Category $category, $stores, int $count)
    {
        $perStore = intdiv($count, $stores->count());
        $remainder = $count % $stores->count();
        $names = self::PRODUCT_NAMES[$category->slug];

        $products = collect();

        foreach ($stores->values() as $index => $store) {
            $storeCount = $perStore + ($index < $remainder ? 1 : 0);

            if ($storeCount === 0) {
                continue;
            }

            $products = $products->merge(
                Product::factory()
                    ->count($storeCount)
                    ->for($category)
                    ->for($store)
                    ->state(fn () => ['name' => $names[array_rand($names)]])
                    ->create()
            );
        }

        return $products;
    }

    /**
     * 150 kullanıcıyı 4 persona'ya eşit, mağazalara rastgele dağıtır.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function seedUsers($stores)
    {
        $personas = [...array_keys(self::PERSONA_CATEGORY_MAP), 'giyim_odakli'];
        $personaCount = count($personas);
        $perPersona = intdiv(self::TOTAL_USERS, $personaCount);
        $remainder = self::TOTAL_USERS % $personaCount;

        $users = collect();

        foreach ($personas as $index => $persona) {
            $count = $perPersona + ($index < $remainder ? 1 : 0);

            $users = $users->merge(
                User::factory()
                    ->count($count)
                    ->state(fn () => [
                        'persona' => $persona,
                        'store_id' => $stores->random()->id,
                    ])
                    ->create()
            );
        }

        return $users;
    }

    /**
     * Her kullanıcı için siparişler üretir. Her siparişteki ürünler
     * %80 ihtimalle kullanıcının kendi kategorisinden (giyim_odakli
     * için kullanıcıya özel rastgele seçilmiş bir giyim alt-kategorisi),
     * %20 ihtimalle diğer kategorilerden rastgele seçilir.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $users
     * @param  \Illuminate\Support\Collection<string, Category>  $categories
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    private function seedOrders($users, $categories, $products, $stores): void
    {
        $productsByStoreAndCategory = $products->groupBy(fn (Product $product) => $product->store_id . '-' . $product->category_id);
        $ordersPerUser = intdiv(self::TOTAL_ORDERS, $users->count());

        foreach ($users as $user) {
            $personaCategory = $this->resolvePersonaCategory($user, $categories);

            $otherCategories = $categories->reject(
                fn (Category $category) => $category->id === $personaCategory->id
            )->values();

            $otherStores = $stores->reject(fn (Store $store) => $store->id === $user->store_id)->values();

            for ($i = 0; $i < $ordersPerUser; $i++) {
                // Kullanıcının bir "ana mağazası" var (users.store_id) ama gerçek
                // hayattaki gibi ara sıra başka mağazalardan da alışveriş yapıyor.
                // Bu, hem her mağazanın kendi müşteri geçmişi olmasını sağlıyor
                // hem de collaborative filtering'e çapraz-mağaza sinyali veriyor.
                $orderStoreId = (mt_rand(1, 100) <= self::HOME_STORE_ORDER_RATE)
                    ? $user->store_id
                    : $otherStores->random()->id;

                $order = Order::create([
                    'user_id' => $user->id,
                    'store_id' => $orderStoreId,
                    'order_date' => now()->subDays(rand(0, 365))->subSeconds(rand(0, 86400)),
                    'total_amount' => 0,
                ]);

                $itemCount = rand(1, 4);
                $total = 0;

                for ($j = 0; $j < $itemCount; $j++) {
                    $category = (mt_rand(1, 100) <= 80)
                        ? $personaCategory
                        : $otherCategories->random();

                    $key = $order->store_id . '-' . $category->id;
                    $pool = $productsByStoreAndCategory->get($key);

                    if (! $pool || $pool->isEmpty()) {
                        continue;
                    }

                    $product = $pool->random();
                    $quantity = rand(1, 5);
                    $unitPrice = $product->price;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                    ]);

                    $total += $quantity * $unitPrice;
                }

                $order->update(['total_amount' => $total]);
            }
        }
    }

    /**
     * Kullanıcının %80 dağılımda çekeceği ana kategoriyi belirler.
     * Sabit persona'lar için harita üzerinden, giyim_odakli için ise
     * kullanıcıya özel (bir kere seçilip cache'lenen) rastgele bir
     * giyim alt-kategorisi üzerinden.
     */
    private function resolvePersonaCategory(User $user, $categories): Category
    {
        if (isset(self::PERSONA_CATEGORY_MAP[$user->persona])) {
            return $categories[self::PERSONA_CATEGORY_MAP[$user->persona]];
        }

        $favoriteSlug = self::CLOTHING_CATEGORY_SLUGS[array_rand(self::CLOTHING_CATEGORY_SLUGS)];

        return $categories[$favoriteSlug];
    }
}
