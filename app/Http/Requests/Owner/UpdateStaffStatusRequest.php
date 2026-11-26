<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

/** Disable or re-enable a staff member: the wanted state, not a toggle. */
class UpdateStaffStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('user');
        abort_unless($staff->isBakeryStaff() && $staff->bakery_id === $this->user()->bakery_id, 404);

        return $this->user()->can('manage-users');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['active' => ['required', 'boolean']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['active' => 'الحالة'];
    }
}
