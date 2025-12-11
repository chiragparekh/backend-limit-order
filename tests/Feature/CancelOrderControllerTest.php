<?php

declare(strict_types=1);

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Cknow\Money\Money;

test('unauthenticated user cannot cancel order', function () {
    $order = Order::factory()->create();

    $response = $this->postJson("/api/orders/{$order->id}/cancel");

    $response->assertStatus(401);
});

test('user cannot cancel another user\'s order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $otherUser->id,
        'status' => OrderStatus::OPEN,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order->id}/cancel");

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'You are not authorized to cancel this order',
        ]);
});

test('user can cancel their own open buy order', function () {
    $user = User::factory()->create([
        'balance' => Money::parse('$5000'),
    ]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'side' => OrderSide::BUY,
        'status' => OrderStatus::OPEN,
        'price' => '50000.00000000',
        'amount' => '0.10000000',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order->id}/cancel");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Order cancelled successfully',
        ]);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => OrderStatus::CANCELLED->value,
    ]);
});

test('user can cancel their own open sell order', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '0.50000000',
        'locked_amount' => '0.50000000',
    ]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'side' => OrderSide::SELL,
        'status' => OrderStatus::OPEN,
        'symbol' => 'BTC',
        'amount' => '0.50000000',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order->id}/cancel");

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Order cancelled successfully',
        ]);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => OrderStatus::CANCELLED->value,
    ]);
});

test('cannot cancel already cancelled order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::CANCELLED,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order->id}/cancel");

    $response->assertStatus(400)
        ->assertJson([
            'status' => 'error',
            'message' => 'Unable to cancel order. Order may already be filled or cancelled.',
        ]);
});

test('cannot cancel filled order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::FILLED,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order->id}/cancel");

    $response->assertStatus(400)
        ->assertJson([
            'status' => 'error',
            'message' => 'Unable to cancel order. Order may already be filled or cancelled.',
        ]);
});

test('buy order cancellation refunds balance', function () {
    $user = User::factory()->create([
        'balance' => Money::parse('$5000'),
    ]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'side' => OrderSide::BUY,
        'status' => OrderStatus::OPEN,
        'price' => '50000.00000000',
        'amount' => '0.10000000', // Total: 5000
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order->id}/cancel");

    $user->refresh();
    expect($user->balance->getAmount())->toBe(Money::parse('$10000')->getAmount());
});

test('sell order cancellation unlocks asset', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => '0.50000000',
        'locked_amount' => '0.50000000',
    ]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'side' => OrderSide::SELL,
        'status' => OrderStatus::OPEN,
        'symbol' => 'BTC',
        'amount' => '0.50000000',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/orders/{$order->id}/cancel");

    $asset->refresh();
    expect($asset->amount)->toBe('1.00000000')
        ->and($asset->locked_amount)->toBe('0.00000000');
});
