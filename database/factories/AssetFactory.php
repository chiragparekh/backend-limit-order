<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 0, 999999.99);
        $lockedAmount = $this->faker->randomFloat(2, 0, $amount);

        return [
            'user_id' => User::factory(),
            'symbol' => $this->faker->randomElement(['BTC', 'ETH', 'USD', 'USDT', 'BNB', 'SOL', 'ADA', 'DOT', 'MATIC', 'AVAX']),
            'amount' => $amount,
            'locked_amount' => $lockedAmount,
        ];
    }
}
