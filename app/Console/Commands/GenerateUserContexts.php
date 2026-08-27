<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\User;
use App\Services\UserContextGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('context:generate')]
#[Description('Her (kullanıcı, mağaza) çifti için deterministik bir context .md dosyası üretir.')]
class GenerateUserContexts extends Command
{
    public function handle(UserContextGenerator $generator): int
    {
        $users = User::all();
        $stores = Store::all();
        $total = $users->count() * $stores->count();

        $this->info("{$users->count()} kullanıcı x {$stores->count()} mağaza = {$total} context dosyası üretilecek.");
        $bar = $this->output->createProgressBar($total);

        foreach ($users as $user) {
            foreach ($stores as $store) {
                $generator->generate($user, $store);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('Tamamlandı.');

        return self::SUCCESS;
    }
}
