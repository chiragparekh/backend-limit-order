<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Jobs\MatchOrderJob;
use App\Models\Order;
use App\Models\User;
use Cknow\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuyOrderAction
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
                        'side' => OrderSide::BUY,
                        'status' => OrderStatus::OPEN,
                    ]);

                $userBalance = $user->balance;

                /** @var numeric-string $amount */
                $amount = $data['amount'];
                /** @var numeric-string $price */
                $price = $data['price'];

                $totalAmount = Money::parse(bcmul($amount, $price, 8));

                /** @var User $user */
                $user = User::query()
                    ->where('id', $user->id)
                    ->lockForUpdate()
                    ->first();

                $user->balance = $userBalance->subtract($totalAmount);
                $user->save();

                dispatch(new MatchOrderJob($order->id));

                return $order;
            });
        } catch (\Throwable $exception) {
            Log::error("[BuyOrderAction] Error during order creation: {$exception->getMessage()}");

            return null;
        }
    }
}
