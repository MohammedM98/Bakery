<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\PreventsDuplicateSubmission;
use Illuminate\Foundation\Http\FormRequest;

/** Create and update share the rules; the opening flour balance is only accepted on create. */
class CustomerRequest extends FormRequest
{
    use PreventsDuplicateSubmission;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->isMethod('post')) {
            $rules += $this->submissionRules();
            // An opening balance is flour credit: only for whoever may record flour deposits.
            $rules['flour_balance_kg'] = $this->user()->can('record-deposit')
                ? ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:100000']
                : ['prohibited'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'اسم العميل',
            'mobile_number' => 'رقم الجوال',
            'flour_balance_kg' => 'رصيد الطحين الابتدائي',
            'notes' => 'الملاحظات',
        ];
    }
}
