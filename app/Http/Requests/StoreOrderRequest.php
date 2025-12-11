<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderSide;
use App\Models\Asset;
use App\Models\User;
use Cknow\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'symbol' => ['required', 'string', 'max:255', 'in:BTC,ETH'],
            'side' => ['required', 'string', 'in:'.implode(',', OrderSide::values())],
            'price' => ['required', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var User $user */
            $user = $this->user();
            $side = $this->enum('side', OrderSide::class);
            $price = $this->float('price');
            $amount = $this->float('amount');
            $symbol = $this->string('symbol');

            $totalPrice = Money::parse($price * $amount);
            $userBalance = $user->balance;

            if ($side === OrderSide::BUY && $userBalance->lessThan($totalPrice)) {
                $validator->errors()->add('symbol', 'No sufficient balance to buy');

                return;
            }

            /** @var Asset $asset */
            $asset = $user->assets()->where('symbol', $symbol)->first();

            if ($side === OrderSide::SELL) {
                if (blank($asset)) {
                    $validator->errors()->add('symbol', 'Asset not available to sell');

                    return;
                }

                if ($asset->amount < $amount) {
                    $validator->errors()->add('symbol', 'No sufficient asset to sell');
                }
            }
        });
    }
}
