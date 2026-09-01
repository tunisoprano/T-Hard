<?php

namespace App\Observers;

use App\Models\Order;
use App\Jobs\GenerateUserContextJob;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Sipariş oluştuğu anda (seeder, tinker, endpoint fark etmez)
        // context dosyasını güncellemesi için Job'u kuyruğa atıyoruz.
        // 
        // ÖNEMLİ DETAY: afterCommit() kullandığımız için, bu Job veritabanı 
        // transaction'ı (Order ve OrderItem'ların kaydedilmesi) TAMAMEN
        // bittikten sonra RabbitMQ'ya gönderilecektir. Böylece Job çalıştığında 
        // sipariş kalemlerini veritabanında kesinlikle bulur.
        GenerateUserContextJob::dispatch($order->user_id, $order->store_id)->afterCommit();
    }
}
