<?php

declare(strict_types=1);

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Jobs\MatchOrderJob;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Cknow\Money\Money;
use Illuminate\Support\Facades\Log;

test('matches buy order with sell order at equal or lower price', function () {
    $buyer = User::factory()->create(['balance' => Money::parse(10000)]);
    $seller = User::factory()->create(['balance' => Money::parse(1000)]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 50000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'price' => 49000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => 1.0,
        'locked_amount' => 0.5,
    ]);

    (new MatchOrderJob($buyOrder->id))->handle();

    $buyOrder->refresh();
    $sellOrder->refresh();
    $buyer->refresh();
    $seller->refresh();

    expect($buyOrder->status)->toBe(OrderStatus::FILLED)
        ->and($sellOrder->status)->toBe(OrderStatus::FILLED)
        ->and($buyer->assets()->where('symbol', 'BTC')->first()->amount)->toBe('0.50000000')
        ->and($seller->assets()->where('symbol', 'BTC')->first()->locked_amount)->toBe('0.00000000')
        ->and($seller->balance->getAmount())->toBeGreaterThan(Money::parse(1000)->getAmount());
});

test('matches sell order with buy order at equal or higher price', function () {
    $seller = User::factory()->create(['balance' => Money::parse(1000)]);
    $buyer = User::factory()->create(['balance' => Money::parse(10000)]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'ETH',
        'side' => OrderSide::SELL,
        'price' => 3000,
        'amount' => 1.0,
        'status' => OrderStatus::OPEN,
    ]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'ETH',
        'side' => OrderSide::BUY,
        'price' => 3100,
        'amount' => 1.0,
        'status' => OrderStatus::OPEN,
    ]);

    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'ETH',
        'amount' => 2.0,
        'locked_amount' => 1.0,
    ]);

    (new MatchOrderJob($sellOrder->id))->handle();

    $sellOrder->refresh();
    $buyOrder->refresh();
    $seller->refresh();
    $buyer->refresh();

    expect($sellOrder->status)->toBe(OrderStatus::FILLED)
        ->and($buyOrder->status)->toBe(OrderStatus::FILLED)
        ->and($buyer->assets()->where('symbol', 'ETH')->first()->amount)->toBe('1.00000000')
        ->and($seller->assets()->where('symbol', 'ETH')->first()->locked_amount)->toBe('0.00000000');
});

test('does not match orders with different symbols', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 50000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'ETH',
        'side' => OrderSide::SELL,
        'price' => 3000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'ETH',
        'amount' => 1.0,
        'locked_amount' => 0.5,
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with("MatchOrderJob: Order #{$buyOrder->id} not found");

    (new MatchOrderJob($buyOrder->id))->handle();

    $buyOrder->refresh();
    $sellOrder->refresh();

    expect($buyOrder->status)->toBe(OrderStatus::OPEN)
        ->and($sellOrder->status)->toBe(OrderStatus::OPEN);
});

test('does not match orders that are not open', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 50000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'price' => 49000,
        'amount' => 0.5,
        'status' => OrderStatus::FILLED,
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with("MatchOrderJob: Order #{$buyOrder->id} not found");

    (new MatchOrderJob($buyOrder->id))->handle();

    $buyOrder->refresh();

    expect($buyOrder->status)->toBe(OrderStatus::OPEN);
});

test('does not match buy order when sell price is too high', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 50000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'price' => 51000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with("MatchOrderJob: Order #{$buyOrder->id} not found");

    (new MatchOrderJob($buyOrder->id))->handle();

    $buyOrder->refresh();

    expect($buyOrder->status)->toBe(OrderStatus::OPEN);
});

test('does not match sell order when buy price is too low', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'price' => 50000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 49000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with("MatchOrderJob: Order #{$sellOrder->id} not found");

    (new MatchOrderJob($sellOrder->id))->handle();

    $sellOrder->refresh();

    expect($sellOrder->status)->toBe(OrderStatus::OPEN);
});

test('selects lowest priced sell order for buy order', function () {
    $buyer = User::factory()->create(['balance' => Money::parse(10000)]);
    $seller1 = User::factory()->create(['balance' => Money::parse(1000)]);
    $seller2 = User::factory()->create(['balance' => Money::parse(1000)]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 50000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    $higherPricedSell = Order::factory()->create([
        'user_id' => $seller1->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'price' => 49000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    $lowerPricedSell = Order::factory()->create([
        'user_id' => $seller2->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'price' => 48000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
        'created_at' => now()->subMinute(),
    ]);

    Asset::factory()->create([
        'user_id' => $seller2->id,
        'symbol' => 'BTC',
        'amount' => 1.0,
        'locked_amount' => 0.5,
    ]);

    (new MatchOrderJob($buyOrder->id))->handle();

    $lowerPricedSell->refresh();
    $higherPricedSell->refresh();

    expect($lowerPricedSell->status)->toBe(OrderStatus::FILLED)
        ->and($higherPricedSell->status)->toBe(OrderStatus::OPEN);
});

test('selects highest priced buy order for sell order', function () {
    $seller = User::factory()->create(['balance' => Money::parse(1000)]);
    $buyer1 = User::factory()->create(['balance' => Money::parse(10000)]);
    $buyer2 = User::factory()->create(['balance' => Money::parse(10000)]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'price' => 50000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    $lowerPricedBuy = Order::factory()->create([
        'user_id' => $buyer1->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 49000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
        'created_at' => now()->subMinute(),
    ]);

    $higherPricedBuy = Order::factory()->create([
        'user_id' => $buyer2->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 51000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => 1.0,
        'locked_amount' => 0.5,
    ]);

    (new MatchOrderJob($sellOrder->id))->handle();

    $higherPricedBuy->refresh();
    $lowerPricedBuy->refresh();

    expect($higherPricedBuy->status)->toBe(OrderStatus::FILLED)
        ->and($lowerPricedBuy->status)->toBe(OrderStatus::OPEN);
});

test('updates buyer balance with fee deduction', function () {
    $buyer = User::factory()->create(['balance' => Money::parse(10000)]);
    $seller = User::factory()->create(['balance' => Money::parse(1000)]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 50000,
        'amount' => 1.0,
        'status' => OrderStatus::OPEN,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'price' => 49000,
        'amount' => 1.0,
        'status' => OrderStatus::OPEN,
    ]);

    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => 2.0,
        'locked_amount' => 1.0,
    ]);

    $initialBuyerBalance = $buyer->balance;

    (new MatchOrderJob($buyOrder->id))->handle();

    $buyer->refresh();

    $tradeValue = Money::parse(1.0 * 49000);
    $expectedFee = $tradeValue->multiply('0.015');
    $expectedBalance = $initialBuyerBalance->subtract($expectedFee);

    expect($buyer->balance->getAmount())->toBe($expectedBalance->getAmount());
});

test('updates seller balance with trade value', function () {
    $buyer = User::factory()->create(['balance' => Money::parse(10000)]);
    $seller = User::factory()->create(['balance' => Money::parse(1000)]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 50000,
        'amount' => 1.0,
        'status' => OrderStatus::OPEN,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'price' => 49000,
        'amount' => 1.0,
        'status' => OrderStatus::OPEN,
    ]);

    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => 2.0,
        'locked_amount' => 1.0,
    ]);

    $initialSellerBalance = $seller->balance;

    (new MatchOrderJob($buyOrder->id))->handle();

    $seller->refresh();

    $tradeValue = Money::parse(1.0 * 49000);
    $expectedBalance = $initialSellerBalance->add($tradeValue);

    expect($seller->balance->getAmount())->toBe($expectedBalance->getAmount());
});

test('creates buyer asset if it does not exist', function () {
    $buyer = User::factory()->create(['balance' => Money::parse(10000)]);
    $seller = User::factory()->create(['balance' => Money::parse(1000)]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 50000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'price' => 49000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => 1.0,
        'locked_amount' => 0.5,
    ]);

    expect($buyer->assets()->where('symbol', 'BTC')->exists())->toBeFalse();

    (new MatchOrderJob($buyOrder->id))->handle();

    $buyerAsset = $buyer->assets()->where('symbol', 'BTC')->first();
    expect($buyerAsset)->not->toBeNull()
        ->and($buyerAsset->amount)->toBe('0.50000000');
});

test('throws exception when seller asset does not exist', function () {
    $buyer = User::factory()->create(['balance' => Money::parse(10000)]);
    $seller = User::factory()->create(['balance' => Money::parse(1000)]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 50000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'price' => 49000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    Log::shouldReceive('error')
        ->once()
        ->with(\Mockery::pattern("/MatchOrderJob: Order #{$buyOrder->id}: Issue while processing: Seller asset not found/"));

    (new MatchOrderJob($buyOrder->id))->handle();

    $buyOrder->refresh();
    $sellOrder->refresh();

    expect($buyOrder->status)->toBe(OrderStatus::OPEN)
        ->and($sellOrder->status)->toBe(OrderStatus::OPEN);
});

test('rolls back transaction on exception', function () {
    $buyer = User::factory()->create(['balance' => Money::parse(10000)]);
    $seller = User::factory()->create(['balance' => Money::parse(1000)]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 50000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'price' => 49000,
        'amount' => 0.5,
        'status' => OrderStatus::OPEN,
    ]);

    // Don't create seller asset to trigger exception
    // Asset::factory()->create([...]) is intentionally omitted

    Log::shouldReceive('error')
        ->once()
        ->with(\Mockery::pattern("/MatchOrderJob: Order #{$buyOrder->id}: Issue while processing: Seller asset not found/"));

    (new MatchOrderJob($buyOrder->id))->handle();

    $buyOrder->refresh();
    $sellOrder->refresh();

    // Orders should remain OPEN due to transaction rollback
    expect($buyOrder->status)->toBe(OrderStatus::OPEN)
        ->and($sellOrder->status)->toBe(OrderStatus::OPEN);
});

test('uses counter order price for trade execution', function () {
    $buyer = User::factory()->create(['balance' => Money::parse('$10000')]);
    $seller = User::factory()->create(['balance' => Money::parse('$1000')]);

    $buyOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY,
        'price' => 50000,
        'amount' => 1.0,
        'status' => OrderStatus::OPEN,
    ]);

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL,
        'price' => 48000,
        'amount' => 1.0,
        'status' => OrderStatus::OPEN,
    ]);

    Asset::factory()->create([
        'user_id' => $seller->id,
        'symbol' => 'BTC',
        'amount' => 2.0,
        'locked_amount' => 1.0,
    ]);

    $initialSellerBalance = $seller->balance;

    (new MatchOrderJob($buyOrder->id))->handle();

    $seller->refresh();

    $tradeValue = Money::parse('$48000');
    $expectedBalance = $initialSellerBalance->add($tradeValue);

    expect($seller->balance->getAmount())->toBe($expectedBalance->getAmount());
});
