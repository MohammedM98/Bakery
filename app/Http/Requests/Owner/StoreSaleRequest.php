<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\PreventsDuplicateSubmission;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cash sale by the kilo. The price is never accepted from the browser: it comes from the bakery settings. */
class StoreSaleRequest extends FormRequest
{
    use PreventsDuplicateSubmission;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'buyer_name' => ['nullable', 'string', 'max:255'],
            'buyer_mobile' => ['nullable', 'string', 'max:50'],
            'kg_amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'payment_status' => ['required', Rule::in([Sale::STATUS_PAID, Sale::STATUS_UNPAID])],
            'sale_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            ...$this->submissionRules(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'buyer_name' => 'اسم المشتري',
            'buyer_mobile' => 'رقم جوال المشتري',
            'kg_amount' => 'الكمية',
            'payment_status' => 'حالة الدفع',
            'sale_date' => 'التاريخ',
            'notes' => 'الملاحظات',
        ];
    }
}
