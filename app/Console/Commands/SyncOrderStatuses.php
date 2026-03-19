<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Ali1688Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOrderStatuses extends Command
{
    protected $signature = 'orders:sync-statuses';
    protected $description = 'Aktiv sifarişlərin statuslarını 1688 ilə sinxronlaşdırır';

    /**
     * Bitməmiş (aktiv) statuslar — yalnız bunlar yoxlanır.
     * success, cancel, terminated artıq final statusdur, yoxlamağa ehtiyac yoxdur.
     */
    private array $activeStatuses = [
        'waitbuyerpay',
        'waitsellersend',
        'waitbuyerreceive',
        'confirm_goods',
    ];

    private array $validStatuses = [
        'waitbuyerpay',
        'waitsellersend',
        'waitbuyerreceive',
        'confirm_goods',
        'success',
        'cancel',
        'terminated',
    ];

    public function handle(): int
    {
        $ali1688 = app(Ali1688Service::class);

        $orders = Order::whereIn('status', $this->activeStatuses)
            ->orderBy('updated_at', 'asc')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Sinxronlaşdırılacaq aktiv sifariş yoxdur.');
            return self::SUCCESS;
        }

        $this->info("Sinxronlaşdırılacaq sifariş sayı: {$orders->count()}");

        $updated = 0;
        $errors = 0;

        foreach ($orders as $order) {
            try {
                $result = $ali1688->getOrderDetail((int) $order->order_id);

                if (!isset($result['result'])) {
                    $this->warn("#{$order->order_id} — cavab alınmadı");
                    $errors++;
                    continue;
                }

                $r = $result['result'];
                $update = [];

                // Status
                $status1688 = $r['baseInfo']['status'] ?? null;
                if ($status1688 && in_array($status1688, $this->validStatuses) && $status1688 !== $order->status) {
                    $update['status'] = $status1688;
                }

                // Products
                $productItems = $r['productItems'] ?? null;
                if ($productItems) {
                    $update['products'] = collect($productItems)->map(fn($item) => [
                        'productID' => $item['productID'] ?? null,
                        'name' => $item['name'] ?? null,
                        'price' => $item['price'] ?? 0,
                        'quantity' => $item['quantity'] ?? 0,
                        'itemAmount' => $item['itemAmount'] ?? 0,
                        'status' => $item['status'] ?? null,
                        'statusStr' => $item['statusStr'] ?? null,
                        'skuID' => $item['skuID'] ?? null,
                        'specId' => $item['specId'] ?? null,
                        'unit' => $item['unit'] ?? null,
                        'productImgUrl' => $item['productImgUrl'][1] ?? $item['productImgUrl'][0] ?? null,
                        'skuInfos' => $item['skuInfos'] ?? [],
                    ])->toArray();
                }

                // Amounts
                if (isset($r['baseInfo']['sumProductPayment'])) {
                    $update['total_amount'] = $r['baseInfo']['sumProductPayment'];
                }
                if (isset($r['baseInfo']['shippingFee'])) {
                    $update['post_fee'] = $r['baseInfo']['shippingFee'];
                }

                if (!empty($update)) {
                    $order->update($update);
                    $updated++;
                    $statusInfo = isset($update['status']) ? " [{$order->status} → {$update['status']}]" : '';
                    $this->line("  #{$order->order_id}{$statusInfo} yeniləndi");
                }

                // 1688 rate limit-ə düşməmək üçün kiçik gecikmə
                usleep(200000); // 200ms
            } catch (\Exception $e) {
                $errors++;
                $this->error("#{$order->order_id} — xəta: {$e->getMessage()}");
                Log::error("Order sync error: {$order->order_id}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Nəticə: {$updated} yeniləndi, {$errors} xəta.");
        return self::SUCCESS;
    }
}
