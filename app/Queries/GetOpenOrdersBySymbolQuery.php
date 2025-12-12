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
    /**
     * @return Builder<Order>|QueryBuilder
     */
    public function handle(?int $userId = null, ?string $symbol = null, ?OrderStatus $status = null, ?OrderSide $side = null): Builder|QueryBuilder
    {
        return Order::query()
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when(! blank($status), fn (Builder $query) => $query->where('status', $status))
            ->when(! blank($symbol), fn (Builder $query) => $query->where('symbol', $symbol))
            ->when(! blank($side), fn (Builder $query) => $query->where('side', $side))
            ->orderBy('price')
            ->orderBy('created_at');
    }
}
