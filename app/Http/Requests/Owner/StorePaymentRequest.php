<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\PreventsDuplicateSubmission;
use Illuminate\Foundation\Http\FormRequest;

/** The "not more than the outstanding balance" check runs inside the action, under a row lock. */
class StorePaymentRequest extends FormRequest
{
    use PreventsDuplicateSubmission;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'payment_date' => ['required', 'date'],
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
            'notes' => 'الملاحظات',
        ];
    }
}
