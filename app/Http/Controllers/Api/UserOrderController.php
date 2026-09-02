<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListUserOrdersRequest;
use App\Models\User;
use App\Services\OrderHistoryService;

class UserOrderController extends Controller
{
    public function __construct(private OrderHistoryService $orderHistoryService) {}

    /**
     * Kullanıcının TÜM mağazalardaki geçmiş siparişlerini, mağazaya göre
     * gruplanmış şekilde döner. Chatbot'un aksine burada mağaza sınırı
     * yok — bu endpoint'in amacı demo/inceleme sırasında "gerçekte veri
     * ne diyor" sorusuna doğrudan cevap vermek.
     */
    public function index(User $user)
    {
        return response()->json(['data' => $this->orderHistoryService->groupedByStore($user)]);
    }

    /**
     * "Siparişlerim" ekranı için: her siparişi AYRI bir satır olarak
     * döner (sipariş no, tarih, ürünler, toplam) — index()'teki gibi
     * ürün bazında özetlemez. `store_id` verilirse sadece o mağazadaki
     * siparişler döner, verilmezse kullanıcının tüm mağazalardaki
     * siparişleri (en yeni önce).
     */
    public function detailed(ListUserOrdersRequest $request, User $user)
    {
        $data = $request->validated();

        $orders = $this->orderHistoryService->detailedFor($user, $data['store_id'] ?? null);

        return response()->json(['data' => $orders]);
    }
}
