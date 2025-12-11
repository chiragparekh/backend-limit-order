<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Jobs\MatchOrderJob;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SellOrderAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): ?Order
    {
        try {
            return DB::transaction(function () use ($user, $data) {
                $order = Order::query()
                    ->create([
                        'user_id' => $user->id,
                        'symbol' => $data['symbol'],
                        'price' => $data['price'],
                        'amount' => $data['amount'],
                        'side' => OrderSide::SELL,
                        'status' => OrderStatus::OPEN,
                    ]);

                /** @var Asset $asset */
                $asset = $user->assets()
                    ->where('symbol', $data['symbol'])
                    ->lockForUpdate()
                    ->first();

                if (blank($asset)) {
                    throw new \Exception('Asset not found');
                }

                assert(is_numeric($asset->amount) && is_numeric($order->amount) && is_numeric($asset->locked_amount));

                $asset->amount = bcsub($asset->amount, $order->amount, 8);
                $asset->locked_amount = bcadd($asset->locked_amount, $order->amount, 8);

                $asset->save();

                dispatch(new MatchOrderJob($order->id));

                return $order;
            });
        } catch (\Throwable $exception) {
            Log::error("[SellOrderAction] Error during order creation: {$exception->getMessage()}");

            return null;
        }
    }
}
