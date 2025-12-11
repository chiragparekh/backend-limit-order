<?php

declare(strict_types=1);

use App\Models\Asset;
use App\Models\User;

test('unauthenticated user cannot access profile', function () {
    $response = $this->getJson('/api/profile');

    $response->assertStatus(401);
});

test('authenticated user can view their profile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/profile');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'balance',
                'assets',
            ],
        ]);
});

test('profile returns user balance', function () {
    $user = User::factory()->create([
        'balance' => 1000.50,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/profile');

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'balance' => '1000.50',
            ],
        ]);
});

test('profile returns empty assets array when user has no assets', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/profile');

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'assets' => [],
            ],
        ]);
});

test('profile returns user assets', function () {
    $user = User::factory()->create();

    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'BTC',
        'amount' => 1.5,
        'locked_amount' => 0.5,
    ]);

    Asset::factory()->create([
        'user_id' => $user->id,
        'symbol' => 'ETH',
        'amount' => 10.25,
        'locked_amount' => 2.0,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/profile');

    $response->assertStatus(200);

    $assets = $response->json('data.assets');
    expect($assets)->toHaveCount(2)
        ->and(collect($assets)->pluck('symbol'))->toContain('BTC', 'ETH');

    $btcAsset = collect($assets)->firstWhere('symbol', 'BTC');
    expect($btcAsset)->toMatchArray([
        'symbol' => 'BTC',
        'amount' => '1.50000000',
        'locked_amount' => '0.50000000',
    ]);

    $ethAsset = collect($assets)->firstWhere('symbol', 'ETH');
    expect($ethAsset)->toMatchArray([
        'symbol' => 'ETH',
        'amount' => '10.25000000',
        'locked_amount' => '2.00000000',
    ]);
});

test('profile only returns assets belonging to the authenticated user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Asset::factory()->create([
        'user_id' => $user1->id,
        'symbol' => 'BTC',
    ]);

    Asset::factory()->create([
        'user_id' => $user2->id,
        'symbol' => 'ETH',
    ]);

    $response = $this->actingAs($user1, 'sanctum')
        ->getJson('/api/profile');

    $response->assertStatus(200);

    $assets = $response->json('data.assets');
    expect($assets)->toHaveCount(1)
        ->and($assets[0]['symbol'])->toBe('BTC');

    $response->assertJsonMissing([
        'data' => [
            'assets' => [
                [
                    'symbol' => 'ETH',
                ],
            ],
        ],
    ]);
});
