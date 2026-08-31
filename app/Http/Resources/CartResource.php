<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sepeti JSON'a çevirme mantığı (eskiden CartController::formatCart).
 * Bu iş mantığı değil, sunum/serileştirme (presentation) — Laravel'de bu
 * tam olarak API Resource'un işi, genel bir "servis"e koymak yanlış
 * soyutlama olurdu.
 *
 * $wrap = null: Laravel Resource'ları varsayılan olarak çıktıyı
 * {"data": {...}} şeklinde sarar. Frontend zaten sarmasız
 * {"cart_id":..., "items":[...], "total":...} formatını beklediği için
 * bunu kapatıyoruz.
 */
class CartResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $items = $this->items->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'name' => $item->product->name,
            'category' => $item->product->category->name,
            'price' => $item->product->price,
            'quantity' => $item->quantity,
            'subtotal' => round($item->quantity * $item->product->price, 2),
        ]);

        return [
            'cart_id' => $this->id,
            'items' => $items,
            'total' => round($items->sum('subtotal'), 2),
        ];
    }
}
