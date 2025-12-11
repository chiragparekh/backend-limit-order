<?php

declare(strict_types=1);

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\User;
use Cknow\Money\Money;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('unauthenticated user cannot create order', function () {
    $response = $this->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => 50000,
        'amount' => 0.1,
    ]);

    $response->assertStatus(401);
});

test('authenticated user can create buy order', function () {
    $user = User::factory()->create([
        'balance' => Money::parse('$10000'),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000,
            'amount' => 0.1,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Order placed successfully',
        ]);

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => OrderSide::BUY->value,
        'price' => '50000.00000000',
        'amount' => '0.10000000',
        'status' => OrderStatus::OPEN->value,
    ]);
});

test('authenticated user can create sell order', function () {
    $user = User::factory()->create();
    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => 1.0,
        'locked_amount' => 0,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000,
            'amount' => 0.5,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'Order placed successfully',
        ]);

    $this->assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'side' => OrderSide::SELL->value,
        'price' => '50000.00000000',
        'amount' => '0.50000000',
        'status' => OrderStatus::OPEN->value,
    ]);
});

test('order creation requires symbol field', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'side' => 'buy',
            'price' => 50000,
            'amount' => 0.1,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['symbol']);
});

test('order creation requires side field', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'price' => 50000,
            'amount' => 0.1,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['side']);
});

test('order creation requires price field', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'amount' => 0.1,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['price']);
});

test('order creation requires amount field', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('symbol must be valid', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'INVALID',
            'side' => 'buy',
            'price' => 50000,
            'amount' => 0.1,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['symbol']);
});

test('symbol must be BTC or ETH', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'ETH',
            'side' => 'buy',
            'price' => 3000,
            'amount' => 1.0,
        ]);

    $response->assertStatus(200);
});

test('side must be valid', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'invalid',
            'price' => 50000,
            'amount' => 0.1,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['side']);
});

test('side must be buy or sell', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000,
            'amount' => 0.1,
        ]);

    // Will fail validation because user doesn't have asset, but side validation passes
    $response->assertStatus(422);
    expect($response->json('errors.side'))->toBeNull();
});

test('price must be numeric', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 'not-a-number',
            'amount' => 0.1,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['price']);
});

test('price must be non-negative', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => -100,
            'amount' => 0.1,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['price']);
});

test('amount must be numeric', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000,
            'amount' => 'not-a-number',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('amount must be non-negative', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000,
            'amount' => -0.1,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('buy order fails when user has insufficient balance', function () {
    $user = User::factory()->create([
        'balance' => Money::parse(1000),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000,
            'amount' => 0.1, // Total: 5000, but user only has 1000
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['symbol'])
        ->assertJson([
            'errors' => [
                'symbol' => ['No sufficient balance to buy'],
            ],
        ]);
});

test('sell order fails when user has no asset', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000,
            'amount' => 0.1,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['symbol'])
        ->assertJson([
            'errors' => [
                'symbol' => ['Asset not available to sell'],
            ],
        ]);
});

test('sell order fails when user has insufficient asset amount', function () {
    $user = User::factory()->create();
    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => 0.5,
        'locked_amount' => 0,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000,
            'amount' => 1.0, // User only has 0.5
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['symbol'])
        ->assertJson([
            'errors' => [
                'symbol' => ['No sufficient asset to sell'],
            ],
        ]);
});

test('buy order deducts balance from user', function () {
    $user = User::factory()->create([
        'balance' => Money::parse('$10000'),
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 50000,
            'amount' => 0.1, // Total: 5000
        ]);

    $user->refresh();
    expect($user->balance->getAmount())->toBe(Money::parse('$5000')->getAmount());
});

test('sell order locks asset amount', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => 1.0,
        'locked_amount' => 0,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 50000,
            'amount' => 0.5,
        ]);

    $asset->refresh();
    expect($asset->amount)->toBe('0.50000000')
        ->and($asset->locked_amount)->toBe('0.50000000');
});
