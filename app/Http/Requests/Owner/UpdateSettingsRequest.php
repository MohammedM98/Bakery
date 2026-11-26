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
            'regular_price_per_kg' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'flour_exchange_fee_per_kg' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'regular_price_per_kg' => 'سعر الكيلو',
            'flour_exchange_fee_per_kg' => 'رسم تبديل القمح للكيلو',
        ];
    }
}
