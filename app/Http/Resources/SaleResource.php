<?php

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A sale as the website's lists show it (Sale::toRow), with display texts. Load `customer:id,name` first.
 *
 * @mixin Sale
 */
class SaleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->toRow();

        return [
            ...$row,
            'price_per_kg' => $this->price_per_kg,
            'kg_text' => kg($row['kg_amount']),
            'total_text' => money($row['total_amount']),
            'remaining_text' => money($row['remaining_amount']),
            'payment_status_label' => $this->isPaid() ? 'مدفوع' : ($this->isPartiallyPaid() ? 'مدفوع جزئيًا' : 'غير مدفوع'),
        ];
    }
}
