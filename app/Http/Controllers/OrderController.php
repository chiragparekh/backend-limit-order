<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuyOrderAction;
use App\Actions\SellOrderAction;
use App\Enums\OrderSide;
use App\Http\Requests\StoreOrderRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function store(
        StoreOrderRequest $request,
        BuyOrderAction $buyOrderAction,
        SellOrderAction $sellOrderAction,
    ): JsonResponse {
        $side = $request->enum('side', OrderSide::class);

        /** @var User $user */
        $user = $request->user();

        if ($side === OrderSide::BUY) {
            $order = $buyOrderAction->handle($user, [
                'symbol' => $request->string('symbol'),
                'price' => $request->string('price')->toString(),
                'amount' => $request->string('amount')->toString(),
            ]);

            if (blank($order)) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Unable to buy order',
                ]);
            }
        }

        if ($side === OrderSide::SELL) {
            $order = $sellOrderAction->handle($user, [
                'symbol' => $request->string('symbol'),
                'price' => $request->string('price')->toString(),
                'amount' => $request->string('amount')->toString(),
            ]);

            if (blank($order)) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Unable to sell order',
                ]);
            }
        }

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Order placed successfully',
        ]);
    }
}
