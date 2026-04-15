<?php

namespace App\Console\Commands;

use App\Models\Category1688;
use App\Services\Ali1688Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCategoryProductCounts extends Command
{
    protected $signature = 'categories:sync-product-counts
                            {--only-active : Yalnız active kateqoriyalar}
                            {--chunk=100 : Batch ölçüsü}
                            {--sleep=200 : Hər sorğu arası ms gecikmə}';

    protected $description = 'Hər kateqoriyanın məhsul sayını birbaşa 1688-dən yeniləyir';

    public function handle(): int
    {
        $ali1688 = app(Ali1688Service::class);
        $palletId = config('services.ali1688.offer_pool_id');

        if (!$palletId) {
            $this->error('services.ali1688.offer_pool_id konfiqurasiyada yoxdur.');
            return self::FAILURE;
        }

        $onlyActive = (bool) $this->option('only-active');
        $chunkSize  = (int) $this->option('chunk');
        $sleepMs    = (int) $this->option('sleep');

        $query = Category1688::query();
        if ($onlyActive) {
            $query->where('status', 'active');
        }

        $total = $query->count();
        if ($total === 0) {
            $this->warn('Kateqoriya tapılmadı.');
            return self::SUCCESS;
        }

        $this->info("Kateqoriya sayı: {$total}");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $errors  = 0;

        $query->orderBy('category_id')->chunk($chunkSize, function ($categories) use (
            $ali1688, $palletId, $sleepMs, &$updated, &$errors, $bar
        ) {
            foreach ($categories as $cat) {
                try {
                    $result = $ali1688->getProductTotal($palletId, (string) $cat->category_id);
                    $count  = $this->extractCount($result);

                    if ($count === null) {
                        $errors++;
                        Log::warning('Category product count: unexpected response', [
                            'category_id' => $cat->category_id,
                            'response' => $result,
                        ]);
                    } else {
                        $cat->update([
                            'product_count' => $count,
                            'product_count_updated_at' => now(),
                        ]);
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('Category product count sync failed', [
                        'category_id' => $cat->category_id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $bar->advance();
                if ($sleepMs > 0) usleep($sleepMs * 1000);
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Nəticə: {$updated} yeniləndi, {$errors} xəta.");

        return self::SUCCESS;
    }

    /**
     * 1688 response-undan totalRecord-u mümkün olan bütün yerlərdə axtar
     */
    private function extractCount(array $result): ?int
    {
        $candidates = [
            $result['result']['model'] ?? null,
            $result['result']['result']['model'] ?? null,
            $result['result']['result']['totalRecord'] ?? null,
            $result['result']['totalRecord'] ?? null,
            $result['result']['result']['total'] ?? null,
            $result['result']['total'] ?? null,
            $result['totalRecord'] ?? null,
            $result['total'] ?? null,
        ];

        foreach ($candidates as $c) {
            if (is_numeric($c)) {
                return (int) $c;
            }
        }

        return null;
    }
}
