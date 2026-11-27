<?php

namespace App\Http\Resources;

use App\Http\Controllers\Owner\SalesLogController;
use App\Models\Movement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One movement. `amount` is signed (positive = عليه, negative = له); `amount_text` is the size without a sign
 * ("30.00 شيكل" or "30 كغ") and `direction_label` says عليه / له, so a flour line never shows a sign.
 *
 * @mixin Movement
 */
class MovementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $cents = Movement::cents($this->amount);
        $size = Movement::decimal(abs($cents));

        return [
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'sale_id' => $this->sale_id,
            'ledger' => $this->ledger,
            'type' => $this->type,
            'type_label' => SalesLogController::TYPE_LABELS[$this->type] ?? $this->type,
            'date' => $this->movement_date->toDateString(),
            'amount' => $this->amount,
            'direction' => $cents >= 0 ? 'debit' : 'credit',
            'direction_label' => $cents >= 0 ? 'عليه' : 'له',
            'amount_text' => $this->ledger === Movement::LEDGER_MONEY ? money($size) : kg($size),
            'description' => $this->description,
            'note' => $this->note,
            'transfer' => $this->type === Movement::TYPE_PAYMENT ? ($this->from_bank ? $this->from_bank.' ← '.$this->to_bank : 'نقدًا') : null,
            'transfer_reference' => $this->transfer_reference,
            'recorded_by' => $this->recorded_by_name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
