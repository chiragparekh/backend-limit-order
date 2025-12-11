<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Cknow\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelOrderAction
{
    public function handle(User $user, Order $order): bool
    {
        try {
            return DB::transaction(function () use ($user, $order) {
                /** @var Order $order */
                $order = Order::query()
                    ->where('id', $order->id)
                    ->where('user_id', $user->id)
                    ->where('status', OrderStatus::OPEN)
                    ->lockForUpdate()
                    ->first();

                if (blank($order)) {
                    return false;
                }

                if ($order->side === OrderSide::BUY) {
                    /** @var numeric-string $amount */
                    $amount = $order->amount;
                    /** @var numeric-string $price */
                    $price = $order->price;

                    $totalAmount = Money::parse(bcmul($amount, $price, 8));

                    /** @var User $user */
                    $user = User::query()
                        ->where('id', $user->id)
                        ->lockForUpdate()
                        ->first();

                    $user->balance = $user->balance->add($totalAmount);
                    $user->save();
                }

                if ($order->side === OrderSide::SELL) {
                    /** @var Asset $asset */
                    $asset = $user->assets()
                        ->where('symbol', $order->symbol)
                        ->lockForUpdate()
                        ->first();

                    if (blank($asset)) {
                        throw new \Exception('Asset not found');
                    }

                    assert(is_numeric($asset->amount) && is_numeric($order->amount) && is_numeric($asset->locked_amount));

                    $asset->locked_amount = bcsub($asset->locked_amount, $order->amount, 8);
                    $asset->amount = bcadd($asset->amount, $order->amount, 8);

                    $asset->save();
                }

                $order->status = OrderStatus::CANCELLED;
                $order->save();

                return true;
            });
        } catch (\Throwable $exception) {
            Log::error("[CancelOrderAction] Error during order cancellation: {$exception->getMessage()}");

            return false;
        }
    }
}
