<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Add or rename a bank / e-wallet. Names are unique within the bakery. */
class BankRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Another bakery's bank is "not found" before its name is validated.
        abort_if($this->route('bank') && $this->route('bank')->bakery_id !== $this->user()->bakery_id, 404);

        return Gate::allows('manage-banks');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('banks', 'name')->where('bakery_id', $this->user()->bakery_id)->ignore($this->route('bank')),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.unique' => 'هذا الاسم موجود في القائمة.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'الاسم'];
    }
}
