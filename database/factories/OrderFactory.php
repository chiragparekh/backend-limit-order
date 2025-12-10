<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => $this->faker->randomElement(['BTC', 'ETH', 'USD', 'USDT', 'BNB', 'SOL', 'ADA', 'DOT', 'MATIC', 'AVAX']),
            'side' => $this->faker->randomElement(OrderSide::cases()),
            'price' => $this->faker->randomFloat(2, 0.01, 999999.99),
            'amount' => $this->faker->randomFloat(2, 0.01, 999999.99),
            'status' => $this->faker->randomElement(OrderStatus::cases()),
        ];
    }
}
