<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\PreventsDuplicateSubmission;
use App\Models\Movement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAdjustmentRequest extends FormRequest
{
    use PreventsDuplicateSubmission;

    public function authorize(): bool
    {
        return Gate::allows('record-adjustment');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ledger' => ['required', Rule::in([Movement::LEDGER_MONEY, Movement::LEDGER_FLOUR])],
            'direction' => ['required', Rule::in(['debit', 'credit'])],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'],
            'reason' => ['required', 'string', 'max:200'],
            // No future dates: a statement ending today must close on the current balance.
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            // Checked against this customer's movements in the action .
            'corrects_number' => ['nullable', 'integer', 'min:1'],
            ...$this->submissionRules(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'ledger' => 'الكشف',
            'direction' => 'الاتجاه',
            'amount' => 'المبلغ أو الكمية',
            'reason' => 'السبب',
            'movement_date' => 'التاريخ',
            'corrects_number' => 'رقم الحركة',
        ];
    }
}
