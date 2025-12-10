<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Asset
 */
class AssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'symbol' => $this->symbol,
            'amount' => $this->amount->formatByDecimal(),
            'locked_amount' => $this->locked_amount->formatByDecimal(),
        ];
    }
}
