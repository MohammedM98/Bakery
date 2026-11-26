<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\PreventsDuplicateSubmission;
use Illuminate\Foundation\Http\FormRequest;

/** A payment may exceed what the customer owes: the money balance then goes to "له". */
class StorePaymentRequest extends FormRequest
{
    use PreventsDuplicateSubmission;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            // No future dates: a statement ending today must close on the current balance.
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            // Checked against this customer's sales in the action .
            'sale_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            ...$this->submissionRules(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'amount' => 'المبلغ',
            'payment_date' => 'التاريخ',
            'sale_id' => 'البيع',
            'notes' => 'الملاحظات',
        ];
    }
}
