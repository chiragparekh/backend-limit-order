<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Cknow\Money\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatchOrderJob implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        try {
            DB::beginTransaction();

            $order = Order::query()
                ->where('id', $this->orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $side = $order->side;
            $symbol = $order->symbol;

            if ($side === OrderSide::BUY) {
                $counterOrder = Order::query()
                    ->where('status', OrderStatus::OPEN)
                    ->where('symbol', $symbol)
                    ->where('side', OrderSide::SELL)
                    ->where('price', '<=', $order->price)
                    ->orderBy('price')
                    ->oldest()
                    ->lockForUpdate()
                    ->first();
            } else {
                $counterOrder = Order::query()
                    ->where('status', OrderStatus::OPEN)
                    ->where('symbol', $symbol)
                    ->where('side', OrderSide::BUY)
                    ->where('price', '>=', $order->price)
                    ->orderBy('price', 'desc')
                    ->oldest()
                    ->lockForUpdate()
                    ->first();
            }

            if (blank($counterOrder)) {
                Log::info('MatchOrderJob: Order #'.$this->orderId.' not found');
                DB::rollBack();

                return;
            }

            $buyerId = $side === OrderSide::BUY ? $order->user_id : $counterOrder->user_id;
            $sellerId = $side === OrderSide::SELL ? $order->user_id : $counterOrder->user_id;
            $amount = $order->amount;
            $price = $counterOrder->price;

            // Lock users in consistent order (by ID) to prevent deadlocks
            $userIds = [$buyerId, $sellerId];
            sort($userIds);

            $users = User::query()
                ->whereIn('id', $userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $buyer = $users->get($buyerId);
            if (blank($buyer)) {
                Log::error('MatchOrderJob: Order #'.$this->orderId.': Buyer not found');
                DB::rollBack();

                return;
            }

            $seller = $users->get($sellerId);

            if (blank($seller)) {
                Log::error('MatchOrderJob: Order #'.$this->orderId.': Seller not found');
                DB::rollBack();

                return;
            }

            $this->updateUserAssets($buyer, $seller, $symbol, $amount);
            $this->updateUserBalance($buyer, $seller, $amount, $price);
            $this->markOrdersAsFilled($order, $counterOrder);

            event(new OrderMatched($order));
            event(new OrderMatched($counterOrder));

            DB::commit();
        } catch (\Throwable $e) {
            Log::error("MatchOrderJob: Order #{$this->orderId}: Issue while processing: {$e->getMessage()}");
            DB::rollBack();
        }
    }

    /**
     * @throws \Exception
     */
    private function updateUserAssets(User $buyer, User $seller, string $symbol, string $amount): void
    {
        /** @var Asset $buyerAsset */
        $buyerAsset = $buyer->assets()->where('symbol', $symbol)->lockForUpdate()->firstOrCreate([
            'symbol' => $symbol,
        ], [
            'amount' => '0',
            'locked_amount' => '0',
        ]);

        /** @var Asset $sellerAsset */
        $sellerAsset = $seller->assets()->where('symbol', $symbol)->lockForUpdate()->first();

        if (blank($sellerAsset)) {
            throw new \Exception('Seller asset not found');
        }

        assert(is_numeric($sellerAsset->amount) && is_numeric($sellerAsset->locked_amount) && is_numeric($amount));

        $sellerAsset->locked_amount = bcsub($sellerAsset->locked_amount, $amount, 8);
        $sellerAsset->save();

        assert(is_numeric($buyerAsset->amount));

        $buyerAsset->amount = bcadd($buyerAsset->amount, $amount, 8);
        $buyerAsset->save();
    }

    private function markOrdersAsFilled(Order $order, Order $counter): void
    {
        $order->status = OrderStatus::FILLED;
        $counter->status = OrderStatus::FILLED;

        $order->save();
        $counter->save();
    }

    private function updateUserBalance(User $buyer, User $seller, string $amount, string $price): void
    {
        assert(is_numeric($amount) && is_numeric($price));

        $tradeValue = Money::parse(bcmul($amount, $price, 8));
        $fee = $tradeValue->multiply('0.015');

        $buyer->balance = $buyer->balance->subtract($fee);
        $buyer->save();

        $seller->balance = $seller->balance->add($tradeValue);
        $seller->save();
    }
}
