<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class GetOpenOrdersBySymbolQuery
{
    public function handle(?string $symbol = null, ?OrderStatus $status = null, ?OrderSide $side = null): Builder|QueryBuilder
    {
        return Order::query()
            ->when(! blank($status), fn (Builder|QueryBuilder $query) => $query->where('status', $status))
            ->when(! blank($symbol), fn (Builder|QueryBuilder $query) => $query->where('symbol', $symbol))
            ->when(! blank($side), fn (Builder|QueryBuilder $query) => $query->where('side', $side))
            ->orderBy('price')
            ->orderBy('created_at');
    }
}
