<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Her (kullanıcı, mağaza) çifti için, o kullanıcının o mağazadaki
 * satın alma geçmişini ve o mağaza için üretilmiş önerilerini
 * deterministik bir Markdown dosyasına döker.
 *
 * Neden dosyaya yazıyoruz, canlı DB sorgusu yerine? Çünkü chatbot artık
 * her mesajda DB'ye gitmek yerine bu önceden üretilmiş dosyayı okuyacak
 * (bkz. ChatController) — hem daha hızlı hem de "context" tek bir yerde
 * toplanmış oluyor (sepet ekranındaki öneri paneli de aynı dosyayı okuyor).
 *
 * Neden LLM ile değil, deterministik (düz PHP) üretiyoruz? Çünkü 150
 * kullanıcı x 4 mağaza = 600 dosya için LLM çağırmak dakikalar sürer;
 * bu veri zaten yapılandırılmış (sipariş/ürün/kategori), doğal dile
 * çevirmeye gerek yok — düz, okunaklı bir Markdown format LLM'in
 * prompt'una direkt gömülecek kadar yeterli.
 */
class UserContextGenerator
{
    private const TOP_RECOMMENDATIONS = 10;

    public function generate(User $user, Store $store): string
    {
        $orderItems = OrderItem::whereIn(
            'order_id',
            $user->orders()->where('store_id', $store->id)->select('id')
        )->with('product.category')->get();

        $orderCount = $user->orders()->where('store_id', $store->id)->count();
        $totalSpent = $user->orders()->where('store_id', $store->id)->sum('total_amount');

        $categoryTotals = $orderItems->groupBy(fn (OrderItem $item) => $item->product->category->name)
            ->map(fn ($items) => $items->sum('quantity'))
            ->sortDesc();

        $favoriteCategory = $categoryTotals->keys()->first();

        $purchaseLines = $orderItems->groupBy(fn (OrderItem $item) => $item->product->name)
            ->map(fn ($items, $name) => sprintf(
                '- %s (%s) — toplam %d adet',
                $name,
                $items->first()->product->category->name,
                $items->sum('quantity')
            ))
            ->implode("\n");

        // Satır sonundaki [#id] etiketi: LLM bunu görmezden gelip normal
        // metin gibi okur, ama sepet ekranı bu ID'yi ayrıştırıp "Sepete
        // Ekle" butonuna bağlıyor (bkz. CartController::recommendations).
        // İnsan/LLM okunabilirliğini bozmadan, programatik erişim ekliyor.
        $recommendations = $user->recommendations()
            ->where('store_id', $store->id)
            ->with('product.category')
            ->orderBy('rank')
            ->limit(self::TOP_RECOMMENDATIONS)
            ->get()
            ->map(fn ($rec) => sprintf(
                '- %s (%s, %s TL) [#%d]',
                $rec->product->name,
                $rec->product->category->name,
                $rec->product->price,
                $rec->product->id
            ))
            ->implode("\n");

        $markdown = <<<MD
        # Kullanıcı Bağlamı: {$user->name} — {$store->name}

        - Kullanıcı ID: {$user->id}
        - Persona: {$user->persona}
        - Son güncelleme: {$this->now()}

        ## Bu Mağazadaki Sipariş Özeti

        - Toplam sipariş sayısı: {$orderCount}
        - Toplam harcama: {$totalSpent} TL
        - Favori kategori: {$this->orNone($favoriteCategory)}

        ## Bu Mağazadaki Satın Alma Geçmişi

        {$this->orEmpty($purchaseLines, 'Bu müşterinin bu mağazada henüz siparişi yok.')}

        ## Bu Mağaza İçin Önerilen Ürünler

        {$this->orEmpty($recommendations, 'Henüz öneri üretilmemiş.')}
        MD;

        $this->write($user, $store, $markdown);

        return $markdown;
    }

    private function write(User $user, Store $store, string $markdown): void
    {
        Storage::disk('local')->put($this->path($user, $store), $markdown);
    }

    public function path(User $user, Store $store): string
    {
        return "user-contexts/{$store->id}/{$user->id}.md";
    }

    public function read(User $user, Store $store): ?string
    {
        $path = $this->path($user, $store);

        return Storage::disk('local')->exists($path)
            ? Storage::disk('local')->get($path)
            : null;
    }

    /**
     * Kullanıcının TÜM mağazalardaki context dosyalarını, dosya sistemini
     * tarayarak (Store tablosuna DB sorgusu atmadan) bulup birleştirir.
     * Bu tarama SADECE ana dosyayı (bkz. regenerateMaster) üretirken
     * çalışır — chatbot her mesajda bunu değil, önceden üretilmiş ana
     * dosyayı (readMaster) okur.
     *
     * @return array<int, string> her elemanı bir mağazanın markdown içeriği
     */
    private function scanAllStoreFiles(User $user): array
    {
        $contents = [];

        foreach (Storage::disk('local')->directories('user-contexts') as $storeDir) {
            $path = "{$storeDir}/{$user->id}.md";

            if (Storage::disk('local')->exists($path)) {
                $contents[] = Storage::disk('local')->get($path);
            }
        }

        return $contents;
    }

    /**
     * Kullanıcı başına, mağazadan bağımsız TEK bir "ana dosya" üretir —
     * o kullanıcının tüm mağazalardaki context dosyalarının birleşimi.
     *
     * Neden ayrı bir dosya (mağaza dosyalarını her seferinde tarayıp
     * birleştirmek yerine): platform geneli chatbot her mesajda bu
     * birleştirmeyi tekrar tekrar yapmasın diye — iş önceden (context
     * üretimi sırasında) yapılıp tek bir dosyaya donduruluyor, okuma
     * tarafı artık sadece bu dosyayı okuyor, hiçbir tarama yapmıyor.
     */
    public function regenerateMaster(User $user): void
    {
        $contents = $this->scanAllStoreFiles($user);

        $combined = $contents !== []
            ? implode("\n\n---\n\n", $contents)
            : 'Bu kullanıcı için henüz hiçbir mağazada context dosyası üretilmemiş.';

        Storage::disk('local')->put($this->masterPath($user), $combined);
    }

    public function masterPath(User $user): string
    {
        return "user-contexts/{$user->id}.md";
    }

    public function readMaster(User $user): ?string
    {
        $path = $this->masterPath($user);

        return Storage::disk('local')->exists($path)
            ? Storage::disk('local')->get($path)
            : null;
    }

    /**
     * Sepet ekranındaki öneri paneli için: context dosyasındaki "Önerilen
     * Ürünler" bölümünü ayrıştırıp yapılandırılmış bir diziye çevirir.
     * DB'ye gitmez — sadece dosyayı okuyup regex ile satırları parse eder.
     *
     * @return array<int, array{id:int, name:string, category:string, price:string}>
     */
    public function parseRecommendations(User $user, Store $store): array
    {
        $markdown = $this->read($user, $store);

        if ($markdown === null) {
            return [];
        }

        preg_match_all(
            '/^- (.+?) \((.+?), ([\d.]+) TL\) \[#(\d+)\]$/m',
            $markdown,
            $matches,
            PREG_SET_ORDER
        );

        return array_map(fn ($match) => [
            'id' => (int) $match[4],
            'name' => $match[1],
            'category' => $match[2],
            'price' => $match[3],
        ], $matches);
    }

    private function orNone(?string $value): string
    {
        return $value ?? 'yok';
    }

    private function orEmpty(string $value, string $fallback): string
    {
        return $value !== '' ? $value : $fallback;
    }

    private function now(): string
    {
        return now()->toDateTimeString();
    }
}
