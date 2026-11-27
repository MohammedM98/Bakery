<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\PreventsDuplicateSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/** A payment may exceed what the customer owes: the money balance then goes to "له". */
class StorePaymentRequest extends FormRequest
{
    use PreventsDuplicateSubmission;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999'],
            // No future dates: a statement ending today must close on the current balance.
            'payment_date' => ['required', 'date', 'before_or_equal:'.local_today()->toDateString()],
            // Checked against this customer's sales in the action .
            'sale_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'method' => ['nullable', Rule::in(['cash', 'transfer'])], // Missing = cash.
            // Both sides come from the bakery's own list (the same list for sender and receiver).
            'from_bank_id' => ['exclude_unless:method,transfer', 'required', 'integer', $this->bakeryBank()],
            'to_bank_id' => ['exclude_unless:method,transfer', 'required', 'integer', $this->bakeryBank()],
            'transfer_reference' => ['exclude_unless:method,transfer', 'nullable', 'string', 'max:100'],
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
            'method' => 'طريقة الدفع',
            'from_bank_id' => 'الحساب المحوَّل منه',
            'to_bank_id' => 'الحساب المحوَّل إليه',
            'transfer_reference' => 'رقم الحوالة',
        ];
    }

    private function bakeryBank(): Exists
    {
        return Rule::exists('banks', 'id')->where('bakery_id', $this->user()->bakery_id);
    }
}
