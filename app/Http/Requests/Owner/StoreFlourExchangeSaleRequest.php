<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\PreventsDuplicateSubmission;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Bread taken against the customer's flour balance. The fee per kilo comes from the bakery settings. */
class StoreFlourExchangeSaleRequest extends FormRequest
{
    use PreventsDuplicateSubmission;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kg_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:100000'],
            'payment_status' => ['required', Rule::in([Sale::STATUS_PAID, Sale::STATUS_UNPAID])],
            // No future dates: a statement ending today must close on the current balance.
            'sale_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            ...$this->submissionRules(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'kg_amount' => 'الكمية',
            'payment_status' => 'حالة الدفع',
            'sale_date' => 'التاريخ',
            'notes' => 'الملاحظات',
        ];
    }
}
