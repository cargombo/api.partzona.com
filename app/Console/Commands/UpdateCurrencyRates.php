<?php

namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateCurrencyRates extends Command
{
    protected $signature = 'currency:update';
    protected $description = 'Valyuta məzənnələrini valyuta.com API-dən yeniləyir';

    /**
     * Yenilənəcək valyuta cütləri
     */
    private array $pairs = [
        ['from' => 'CNY', 'to' => 'USD'],
        ['from' => 'CNY', 'to' => 'AZN'],
        ['from' => 'USD', 'to' => 'AZN'],
    ];

    public function handle(): int
    {
        $today = now()->format('Y-m-d');

        foreach ($this->pairs as $pair) {
            $from = $pair['from'];
            $to = $pair['to'];

            try {
                $url = "https://www.valyuta.com/api/calculator/{$from}/{$to}/{$today},{$today},{$today}";

                $response = Http::get($url);

                if (!$response->successful()) {
                    $this->error("API xəta: {$from}-{$to} — HTTP {$response->status()}");
                    Log::warning("Currency update failed: {$from}-{$to}", ['status' => $response->status()]);
                    continue;
                }

                $data = $response->json();
                $rate = $data['body'][0]['result'] ?? null;

                if ($rate === null) {
                    $this->error("Rate tapılmadı: {$from}-{$to}");
                    Log::warning("Currency rate not found in response: {$from}-{$to}", ['data' => $data]);
                    continue;
                }

                Currency::updateOrCreate(
                    ['from_currency' => $from, 'to_currency' => $to],
                    ['rate' => $rate, 'rate_date' => $today]
                );

                $this->info("{$from} → {$to}: {$rate}");
            } catch (\Exception $e) {
                $this->error("Xəta: {$from}-{$to} — {$e->getMessage()}");
                Log::error("Currency update error: {$from}-{$to}", ['error' => $e->getMessage()]);
            }
        }

        $this->info('Valyuta məzənnələri yeniləndi.');
        return self::SUCCESS;
    }
}
