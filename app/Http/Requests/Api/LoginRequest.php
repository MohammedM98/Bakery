<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Auth\LoginRequest as WebLoginRequest;

/** The website's login request plus the device name the token is issued to. */
class LoginRequest extends WebLoginRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...parent::rules(), 'device_name' => ['required', 'string', 'max:255']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['email' => 'البريد الإلكتروني', 'password' => 'كلمة المرور', 'device_name' => 'اسم الجهاز'];
    }
}
