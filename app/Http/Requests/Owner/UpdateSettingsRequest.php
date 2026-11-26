<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A sale's total (up to 100,000 kg x price) must fit sales.total_amount, decimal(10,2).
            'regular_price_per_kg' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999.99'],
            'flour_exchange_fee_per_kg' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'regular_price_per_kg' => 'سعر الكيلو',
            'flour_exchange_fee_per_kg' => 'رسم تبديل الطحين للكيلو',
        ];
    }
}
