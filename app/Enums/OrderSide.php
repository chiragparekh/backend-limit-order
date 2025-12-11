<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderSide: string
{
    case BUY = 'buy';
    case SELL = 'sell';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
