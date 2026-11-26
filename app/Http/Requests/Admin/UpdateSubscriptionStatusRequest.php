<?php

namespace App\Http\Requests\Admin;

use App\Models\Bakery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([Bakery::STATUS_ACTIVE, Bakery::STATUS_INACTIVE])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => 'حالة الاشتراك'];
    }
}
