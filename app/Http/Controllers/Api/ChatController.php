<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Models\Store;
use App\Models\User;
use App\Services\OllamaService;
use App\Services\UserContextGenerator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        private OllamaService $ollama,
        private UserContextGenerator $contextGenerator,
    ) {}

    public function respond(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'message' => ['required', 'string', 'max:1000'],
            'store_id' => ['nullable', 'exists:stores,id'],
        ]);

        $user = User::findOrFail($data['user_id']);

        // 1. İlgili kullanıcı ve mağaza (veya platform geneli) için aktif bir oturum bul veya oluştur
        $session = ChatSession::firstOrCreate([
            'user_id' => $user->id,
            'store_id' => $data['store_id'] ?? null,
        ]);

        // 2. Kullanıcının yeni mesajını veritabanına kaydet
        $session->messages()->create([
            'role' => 'user',
            'content' => $data['message'],
        ]);

        // 3. System prompt'unu oluştur. Kullanıcıya özel geçmiş/öneri
        // bilgisi artık canlı DB sorgusuyla değil, önceden üretilmiş
        // context dosyasından okunuyor (bkz. UserContextGenerator).
        // Katalog (ürün/fiyat listesi) istisna: o bir envanterdir, an be
        // an değişebilir, "kullanıcı deneyimi" değildir — hâlâ DB'den
        // canlı çekiliyor.
        $systemPrompt = isset($data['store_id'])
            ? $this->buildStorePrompt(Store::findOrFail($data['store_id']), $user)
            : $this->buildPersonalPrompt($user);

        // 4. Ollama'ya gidecek mesaj listesini hazırla
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // 5. Veritabanındaki eski mesajları sırasıyla listeye ekle
        $history = $session->messages()->latest()->take(10)->get()->reverse();
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        // 6. Cevabı parça parça tarayıcıya akıt, aynı anda tam metni de biriktir.
        // response()->stream() PHP'nin çıktı arabelleğini (output buffer) kapatıp
        // her echo'yu anında istemciye gönderiyor — normal bir response'ta tüm
        // içerik hazır olana kadar tarayıcı hiçbir şey görmezdi.
        return response()->stream(function () use ($messages, $session) {
            $fullReply = $this->ollama->chatStream($messages, function (string $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            });

            // 7. Akış bitince, tam cevabı veritabanına kaydet
            $session->messages()->create([
                'role' => 'assistant',
                'content' => $fullReply,
            ]);
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache',
            // nginx (Herd) yanıtı arabelleğe almasın, parçalar geldikçe göndersin
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Platform genelinde çalışan asistan. Tek bir mağazaya bağlı olmadığı
     * için, kullanıcının bulunduğu TÜM mağazaların context dosyasını
     * (dosya sistemi taranarak, DB'ye gitmeden) okuyup birleştiriyoruz.
     */
    private function buildPersonalPrompt(User $user): string
    {
        $contexts = $this->contextGenerator->readAllForUser($user);

        $combined = $contexts !== []
            ? implode("\n\n---\n\n", $contexts)
            : 'Bu kullanıcı için henüz hiçbir mağazada context dosyası üretilmemiş.';

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
