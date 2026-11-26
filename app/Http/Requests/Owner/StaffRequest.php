<?php

namespace App\Http\Requests\Owner;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add or edit a staff member. Role and bakery are never taken from the browser: the controller sets them. */
class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only this bakery's staff: another bakery's user, or the owner's own account, is "not found".
        $staff = $this->route('user');
        abort_if($staff && (! $staff->isBakeryStaff() || $staff->bakery_id !== $this->user()->bakery_id), 404);

        return $this->user()->can('manage-users');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            // Required when adding; when editing, empty keeps the current password.
            'password' => [$this->route('user') ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(User::permissionKeys())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'هذا البريد مستخدم لحساب آخر.',
            'permissions.*.in' => 'صلاحية غير معروفة.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
            'permissions' => 'الصلاحيات',
        ];
    }
}
