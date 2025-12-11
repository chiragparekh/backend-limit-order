<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'token',
        ])
        ->assertJson([
            'status' => 'success',
        ]);

    expect($response->json('token'))->toBeString();
});

test('login fails with invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJson([
            'errors' => [
                'email' => ['The provided credentials are incorrect.'],
            ],
        ]);
});

test('login fails with non-existent email', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJson([
            'errors' => [
                'email' => ['The provided credentials are incorrect.'],
            ],
        ]);
});

test('login requires email field', function () {
    $response = $this->postJson('/api/login', [
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login requires password field', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('email must be valid email format', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'not-an-email',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('password must be at least 6 characters', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => '12345',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('login token can be used for authenticated requests', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $loginResponse = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $token = $loginResponse->json('token');

    $profileResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/profile');

    $profileResponse->assertStatus(200);
});
