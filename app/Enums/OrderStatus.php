<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: int
{
    case OPEN = 1;
    case FILLED = 2;
    case CANCELLED = 3;
}
