<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\User;
use Cknow\Money\Money;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            User::factory()->create([
                'email' => 'user1@test.com',
                'balance' => Money::parse('$10000'),
                'password' => Hash::make('password'),
            ]),
            User::factory()->create([
                'email' => 'user2@test.com',
                'balance' => Money::parse('$10000'),
                'password' => Hash::make('password'),
            ]),
        ];

        foreach ($users as $user) {
            Asset::factory()->create([
                'user_id' => $user->id,
                'symbol' => 'BTC',
                'amount' => '0.5',
                'locked_amount' => '0',
            ]);

            Asset::factory()->create([
                'user_id' => $user->id,
                'symbol' => 'ETH',
                'amount' => '10',
                'locked_amount' => '0',
            ]);
        }
    }
}
