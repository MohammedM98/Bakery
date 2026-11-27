<?php

namespace App\Http\Resources;

use App\Models\Bakery;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user as the app needs it: role, bakery, subscription, permission keys, prices and the server clock.
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $bakery = $this->bakery;

        return [
            'user' => $this->only('id', 'name', 'email', 'role'),
            'bakery' => $bakery?->only('id', 'name', 'phone', 'address'),
            'subscription' => $bakery ? BakeryResource::subscription($bakery) : null,
            'permissions' => $this->grantedPermissions(),
            'prices' => $bakery ? self::prices($bakery) : null,
            'server_time' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'local_timezone' => config('app.local_timezone'),
            'local_date' => local_today()->toDateString(),
        ];
    }

    /** @return array<string, string> */
    public static function prices(Bakery $bakery): array
    {
        return [
            'regular_price_per_kg' => $bakery->regular_price_per_kg,
            'regular_price_text' => money($bakery->regular_price_per_kg),
            'flour_exchange_fee_per_kg' => $bakery->flour_exchange_fee_per_kg,
            'flour_exchange_fee_text' => money($bakery->flour_exchange_fee_per_kg),
        ];
    }
}
