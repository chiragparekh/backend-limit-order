<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing('assets');

        return [
            'id' => $this->id,
            'balance' => $this->balance->formatByDecimal(),
            'assets' => AssetResource::collection($this->assets),
        ];
    }
}
