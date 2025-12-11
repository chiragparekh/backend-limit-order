<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CancelOrderAction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancelOrderController extends Controller
{
    public function __invoke(
        Request $request,
        Order $order,
        CancelOrderAction $cancelOrderAction,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        abort_if($order->user_id !== $user->id, 403, 'You are not authorized to cancel this order');

        $success = $cancelOrderAction->handle($user, $order);

        if (! $success) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Unable to cancel order. Order may already be filled or cancelled.',
            ], 400);
        }

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Order cancelled successfully',
        ]);
    }
}
