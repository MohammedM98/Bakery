<?php

namespace App\Http\Resources;

use App\Models\Bakery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A bakery for the platform owner, with its subscription state (and its owner when `owners` is loaded).
 *
 * @mixin Bakery
 */
class BakeryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'subscription' => self::subscription($this->resource),
            'owner' => $this->whenLoaded('owners', fn () => $this->owners->first()?->only('name', 'email')),
        ];
    }

    /** @return array{status: string, active: bool, expires_at: ?string, days_left: ?int} */
    public static function subscription(Bakery $bakery): array
    {
        return [
            'status' => $bakery->subscription_status,
            'active' => $bakery->isSubscriptionActive(),
            'expires_at' => $bakery->subscription_expires_at?->toDateString(),
            'days_left' => $bakery->days_until_expiry === null ? null : (int) $bakery->days_until_expiry,
        ];
    }
}
