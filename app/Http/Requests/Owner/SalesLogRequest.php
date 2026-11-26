<?php

namespace App\Http\Requests\Owner;

use App\Http\Controllers\Owner\SalesLogController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** The sales log's period (day / week / month / year around a date) and its table filters. */
class SalesLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view-sales-log');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(array_keys(SalesLogController::PERIODS))],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', Rule::in(array_keys(SalesLogController::FILTERS))],
            // A customer id of this bakery, or "walk_in" for sales to walk-in buyers.
            'customer' => ['nullable', 'regex:/^(\d+|walk_in)$/'],
            'recorded_by' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'period' => 'الفترة',
            'date' => 'التاريخ',
            'type' => 'نوع الحركة',
            'customer' => 'الزبون',
            'recorded_by' => 'من سجّلها',
        ];
    }
}
