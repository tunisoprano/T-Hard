<?php

namespace App\Jobs;

use App\Models\Store;
use App\Models\User;
use App\Services\UserContextGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Tek bir (kullanıcı, mağaza) çifti için context dosyasını üretir ve
 * kullanıcının ana (master) dosyasını günceller. Checkout sonrası
 * dispatch edilir — böylece kullanıcı HTTP yanıtını beklemez.
 */
class GenerateUserContextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 3;

    public function __construct(
        public int $userId,
        public int $storeId,
    ) {}

    public function handle(UserContextGenerator $generator): void
    {
        $user = User::find($this->userId);
        $store = Store::find($this->storeId);

        if (!$user || !$store) {
            Log::warning("[GenerateUserContextJob] Kullanıcı veya mağaza bulunamadı: user={$this->userId}, store={$this->storeId}");
            return;
        }

        $generator->generate($user, $store);

        // Bu mağazanın dosyası değişti, dolayısıyla kullanıcının TÜM
        // mağazaların birleşimi olan ana dosyası da bayatladı — onu da
        // hemen tazeliyoruz (platform geneli chatbot bunu okuyor).
        $generator->regenerateMaster($user);

        Log::info("[GenerateUserContextJob] Context güncellendi: user={$user->name}, store={$store->name}");
    }
}
