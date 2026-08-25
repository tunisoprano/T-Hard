<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\User;
use App\Services\OllamaService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private OllamaService $ollama) {}

    public function respond(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'message' => ['required', 'string', 'max:1000'],
            'store_id' => ['nullable', 'exists:stores,id'],
        ]);

        $user = User::findOrFail($data['user_id']);

        // 1. İlgili kullanıcı ve mağaza (veya platform geneli) için aktif bir oturum bul veya oluştur
        // (Eğer son 1 saat içinde mesajlaşmışsa aynı oturumdan devam et, yoksa yeni oluştur)
        $session = \App\Models\ChatSession::firstOrCreate(
            [
                'user_id' => $user->id,
                'store_id' => $data['store_id'] ?? null,
            ]
        );

        // 2. Kullanıcının yeni mesajını veritabanına kaydet
        $session->messages()->create([
            'role' => 'user',
            'content' => $data['message']
        ]);

        // 3. System prompt'unu her zamanki gibi dinamik olarak oluştur (katalog vs. güncel kalsın diye)
        $systemPrompt = isset($data['store_id'])
            ? $this->buildStorePrompt(Store::findOrFail($data['store_id']), $user)
            : $this->buildPersonalPrompt($user);

        // 4. Ollama'ya gidecek mesaj listesini hazırla
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        // 5. Veritabanındaki eski mesajları sırasıyla listeye ekle (Burası işin kalbi!)
        // Sadece son 10 mesajı alalım ki modelin kafası ve token limiti dolmasın
        $history = $session->messages()->latest()->take(10)->get()->reverse();
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->content
            ];
        }

        // 6. Ollama'ya sor
        $reply = $this->ollama->chat($messages);

        // 7. Gelen cevabı veritabanına kaydet
        $session->messages()->create([
            'role' => 'assistant',
            'content' => $reply
        ]);

        return response()->json(['reply' => $reply]);
    }

    /**
     * Platform genelinde çalışan asistan: kullanıcının kişisel önerilerini bilir.
     */
    private function buildPersonalPrompt(User $user): string
    {
        $recommendations = $user->recommendations()
            ->with(['product.category', 'product.store'])
            ->orderBy('rank')
            ->limit(5)
            ->get();

        $productLines = $recommendations->map(fn ($rec) => sprintf(
            '- %s (%s, %s, %s TL)',
            $rec->product->name,
            $rec->product->category->name,
            $rec->product->store->name,
            $rec->product->price
        ))->implode("\n");

        return <<<PROMPT
        Sen T-Hard adlı bir e-ticaret platformunun alışveriş asistanısın. Türkçe, kısa ve samimi cevap ver.
        Kullanıcının geçmiş satın alma verilerine göre onun için özel olarak seçilmiş ürün önerileri aşağıda listeleniyor.
        Kullanıcı bir şey sorduğunda, ilgiliyse bu önerilerden bahsedebilirsin. Listede olmayan bir ürün icat etme.

        Kullanıcı için öneriler:
        {$productLines}
        PROMPT;
    }

    /**
     * Mağaza sayfasındaki asistan: sadece o mağazanın kataloğunu bilir, ama
     * kullanıcının başka mağazalardaki geçmişine göre hangi kategorilere
     * ilgi duyduğunu da bilir — böylece "sen pantolon seviyorsun, bizim
     * mağazada da şu pantolonlar var" diyebilir.
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

        // ÖNEMLİ: recommendations tablosunda her (kullanıcı, mağaza) çifti için
        // AYRI bir top-10 var — bu yüzden burada sadece $store->id'ye ait
        // önerileri çekiyoruz. Böylece asistan başka bir mağazanın ürününü
        // "senin için seçtik" diye öneremez, sadece kendi kataloğundan önerir.
        $recommendedHere = $user->recommendations()
            ->where('store_id', $store->id)
            ->with('product.category')
            ->orderBy('rank')
            ->limit(5)
            ->get()
            ->map(fn ($rec) => sprintf('- %s (%s, %s TL)', $rec->product->name, $rec->product->category->name, $rec->product->price))
            ->implode("\n");

        $purchaseHistory = $this->buildPurchaseHistory($user, $store);

        return <<<PROMPT
        Sen {$store->name} mağazasının alışveriş asistanısın. Türkçe, kısa ve samimi cevap ver.
        Görevin bu müşteriye SADECE {$store->name} mağazasının ürünlerini pazarlamak.

        KESİN KURALLAR — bunlara asla uyma dışına çıkma:
        1. Sadece aşağıdaki katalogda olan ürünlerden bahsedebilirsin, listede olmayan bir ürün icat etme.
        2. Başka bir mağazadan ürün önerme — sen sadece {$store->name}'i temsil ediyorsun.
        3. Müşterinin bu mağazadaki geçmiş alışverişi sorulursa SADECE aşağıdaki
           "Geçmiş alışverişleri" listesini kullan. Bu listede olmayan bir ürünü
           "aldınız" diye söyleme, uydurma.
        4. Müşterinin BAŞKA mağazalardaki alışverişi sorulursa: bu bilgiye
           erişimin YOK. "Alışveriş yapmamışsınız" veya "hiç alışverişiniz yok"
           gibi bir şey SÖYLEME — bu yanlış bir iddia olur, sen sadece bunu
           bilmiyorsun. Bunun yerine tam olarak şunu söyle: "Bu bilgiye
           erişimim yok, sadece {$store->name}'deki alışverişlerinizi görebiliyorum."
        5. "Yok" ile "bilmiyorum" farklı şeylerdir. Emin olmadığın hiçbir şeyi
           kesin bir dille ("yok", "yapmadınız") ifade etme.
        6. Müşterinin geçmiş alışverişlerini sıralarken aşağıdaki listedeki
           HER kalemi tek tek numaralı liste halinde say. Hiçbir ürünü atlama,
           özetleme veya gruplama. Listede kaç ürün varsa cevabında da o kadar
           ürün olmalı.

        Bu müşteri için {$store->name} kataloğundan özel olarak seçtiğimiz ürünler:
        {$recommendedHere}

        Müşterinin {$store->name} mağazamızdaki geçmiş alışverişleri:
        {$purchaseHistory}

        {$store->name} kataloğu (tamamı):
        {$catalog}
        PROMPT;
    }

    /**
     * Kullanıcının SADECE bu mağazadaki gerçek sipariş geçmişi.
     * Siparişler `orders.store_id` ile mağazaya bağlı olduğu için, her mağaza
     * yalnızca kendi satışlarını görür — bir mağazanın asistanı, müşterinin
     * rakip mağazadan ne aldığını ürün bazında bilemez.
     */
    private function buildPurchaseHistory(User $user, Store $store): string
    {
        $lines = OrderItem::whereIn('order_id', $user->orders()->where('store_id', $store->id)->select('id'))
            ->with('product.category')
            ->get()
            ->groupBy(fn ($item) => $item->product->name)
            ->map(fn ($items, $productName) => sprintf(
                '- %s (%s) — toplam %d adet',
                $productName,
                $items->first()->product->category->name,
                $items->sum('quantity')
            ))
            ->implode("\n");

        return $lines !== '' ? $lines : 'Bu müşterinin mağazamızda henüz geçmiş alışverişi yok.';
    }
}
