<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBakeryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'subscription_months' => ['required', 'integer', 'min:1', 'max:24'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'اسم المخبز',
            'phone' => 'رقم الهاتف',
            'address' => 'العنوان',
            'subscription_months' => 'مدة الاشتراك',
            'owner_name' => 'اسم صاحب المخبز',
            'owner_email' => 'البريد الإلكتروني',
            'owner_password' => 'كلمة المرور',
        ];
    }
}
