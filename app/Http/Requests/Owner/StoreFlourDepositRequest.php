<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\PreventsDuplicateSubmission;
use Illuminate\Foundation\Http\FormRequest;

class StoreFlourDepositRequest extends FormRequest
{
    use PreventsDuplicateSubmission;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kg_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:100000'],
            // No future dates: a statement ending today must close on the current balance.
            'deposit_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            ...$this->submissionRules(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'kg_amount' => 'الكمية',
            'deposit_date' => 'التاريخ',
            'notes' => 'الملاحظات',
        ];
    }
}
