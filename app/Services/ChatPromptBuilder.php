<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;

/**
 * Chatbot'a gidecek system prompt'unu oluşturur. Mağaza seçilmişse
 * mağaza-özel (katalog + o mağazanın context dosyası), seçilmemişse
 * platform geneli (kullanıcının TÜM mağazalardaki context dosyaları
 * birleştirilmiş) prompt üretir.
 *
 * Neden ayrı bir servis: bu mantık ChatController'ın private metoduydu,
 * ama gerçek iş mantığı (hangi kurallarla, hangi veriyle prompt kurulur)
 * barındırıyor — controller'ın sorumluluğu değil, test edilebilir ve
 * tekrar kullanılabilir olmalı.
 */
class ChatPromptBuilder
{
    public function __construct(private UserContextGenerator $contextGenerator) {}

    public function build(User $user, ?Store $store): string
    {
        return $store !== null
            ? $this->buildStorePrompt($store, $user)
            : $this->buildPersonalPrompt($user);
    }

    /**
     * Platform genelinde çalışan asistan. Tek bir mağazaya bağlı olmadığı
     * için, kullanıcının önceden üretilmiş TEK ana dosyasını (bkz.
     * UserContextGenerator::regenerateMaster) okuyor — burada dosya
     * sistemi taraması YAPILMIYOR, o iş context üretimi sırasında zaten
     * yapılıp bu dosyaya donduruldu.
     */
    private function buildPersonalPrompt(User $user): string
    {
        $combined = $this->contextGenerator->readMaster($user)
            ?? 'Bu kullanıcı için henüz hiçbir mağazada context dosyası üretilmemiş.';

        return <<<PROMPT
        Sen T-Hard adlı bir e-ticaret platformunun alışveriş asistanısın. Türkçe, kısa ve samimi cevap ver.
        Aşağıda kullanıcının alışveriş yaptığı HER mağazadan (---  ile ayrılmış) bir bağlam dosyası var:
        geçmiş siparişleri, favori kategorisi ve o mağaza için üretilmiş ürün önerileri.

        KESİN KURALLAR:
        1. Sadece aşağıdaki dosyalarda geçen ürün/mağaza isimlerini kullan, icat etme.
        2. Bir bilgi (örn. belirli bir mağazadaki alışveriş) dosyalarda yoksa "bu bilgi bende yok" de,
           kesin bir dille ("almadınız", "yok") yanlış iddiada bulunma.

        Kullanıcının mağaza bağlamları:
        {$combined}
        PROMPT;
    }

    /**
     * Mağaza sayfasındaki asistan: sadece o mağazanın kataloğunu (canlı DB'den,
     * çünkü envanter an be an değişebilir) ve o mağaza için önceden üretilmiş
     * context dosyasını (geçmiş + öneri, DB'ye gitmeden) bilir.
     */
    private function buildStorePrompt(Store $store, User $user): string
    {
        $catalog = $store->products()
            ->with('category')
            ->get()
            ->groupBy(fn ($product) => $product->category->name)
            ->map(fn ($products, $categoryName) => $categoryName.': '.$products
                ->map(fn ($p) => $p->name.' ('.$p->price.' TL)')
                ->unique()
                ->implode(', '))
            ->implode("\n");

        $context = $this->contextGenerator->read($user, $store)
            ?? "Bu kullanıcı için henüz context dosyası üretilmemiş (php artisan context:generate çalıştırılmamış olabilir).";

        return <<<PROMPT
        Sen {$store->name} mağazasının alışveriş asistanısın. Türkçe, kısa ve samimi cevap ver.
        Görevin bu müşteriye SADECE {$store->name} mağazasının ürünlerini pazarlamak.

        KESİN KURALLAR — bunlara asla uyma dışına çıkma:
        1. Sadece aşağıdaki katalogda olan ürünlerden bahsedebilirsin, listede olmayan bir ürün icat etme.
        2. Başka bir mağazadan ürün önerme — sen sadece {$store->name}'i temsil ediyorsun.
        3. Müşterinin geçmiş alışverişi/önerileri sorulursa SADECE aşağıdaki "Kullanıcı Bağlamı"
           dosyasını kullan. Orada olmayan bir ürünü "aldınız" diye söyleme, uydurma.
        4. Kullanıcı Bağlamı dosyasında olmayan bir bilgi (örn. başka mağazadaki alışverişi)
           sorulursa: "Alışveriş yapmamışsınız" gibi kesin bir iddiada bulunma, sadece
           "Bu bilgiye erişimim yok, sadece {$store->name}'deki bilgilerinizi görebiliyorum." de.
        5. "Yok" ile "bilmiyorum" farklı şeylerdir. Emin olmadığın hiçbir şeyi kesin bir dille ifade etme.
        6. Kullanıcı Bağlamı'ndaki "Satın Alma Geçmişi" listesindeki HER kalemi tek tek say,
           hiçbirini atlama, özetleme veya gruplama.
        7. "Önerilen Ürünler" listesindeki ürünleri paylaşırken TEK YAPMAN GEREKEN: "Size
           önerebileceğim ürünler şunlar:" yazıp altına listeyi vermek. Listeden SONRA hiçbir
           cümle EKLEME — "ilginizi çektiğini düşündüğüm için", "genellikle tercih ettiğiniz",
           "kategorinize uygun" gibi TEK BİR yorum/gerekçe cümlesi bile yazma, başka mağazadan
           bahsetme. Cevabın listeyle bitsin, liste sonrası boş bırak.

        Kullanıcı Bağlamı ({$store->name}):
        {$context}

        {$store->name} kataloğu (tamamı):
        {$catalog}
        PROMPT;
    }
}
