<?php

namespace App\Http\Resources;

use App\Models\Customer;
use App\Models\Movement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer with both balances: money by sign ("-50.00 شيكل" = له), flour in words ("له 20 كغ").
 * Uses a preloaded `money_balance` sum (withSum alias) when the query has one.
 *
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $money = array_key_exists('money_balance', $this->resource->getAttributes())
            ? Movement::cents($this->resource->getAttributes()['money_balance'])
            : $this->balanceCents(Movement::LEDGER_MONEY);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'mobile_number' => $this->mobile_number,
            'notes' => $this->notes,
            'money_balance' => Movement::decimal($money),
            'money_balance_text' => money(Movement::decimal($money)),
            'flour_balance' => Movement::decimal(Movement::cents($this->flour_balance_kg)),
            'flour_balance_text' => flour_balance($this->flour_balance_kg),
        ];
    }
}
