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

        $recommendations = $user->recommendations()
            ->where('store_id', $store->id)
            ->with('product.category')
            ->orderBy('rank')
            ->limit(self::TOP_RECOMMENDATIONS)
            ->get()
            ->map(fn ($rec) => sprintf(
                '- %s (%s, %s TL)',
                $rec->product->name,
                $rec->product->category->name,
                $rec->product->price
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
