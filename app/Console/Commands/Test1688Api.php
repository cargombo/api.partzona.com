<?php

namespace App\Console\Commands;

use App\Services\Ali1688Service;
use Illuminate\Console\Command;

class Test1688Api extends Command
{
    protected $signature = 'test:1688 {categoryId?}';
    protected $description = '1688 API-ni test et';

    public function handle(Ali1688Service $ali1688): void
    {
        $categoryId = $this->argument('categoryId');
        $poolId = config('services.ali1688.offer_pool_id');

        $this->info("Pool ID: {$poolId}");
        $this->info("Category ID: " . ($categoryId ?? 'yoxdur'));
        $this->info("Sorğu göndərilir...");

        $result = $ali1688->pullOffer(
            $poolId,
            1,
            5,
            $categoryId
        );

        dd($result);
    }
}
