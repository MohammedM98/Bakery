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
            'kg_amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'deposit_date' => ['required', 'date'],
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
