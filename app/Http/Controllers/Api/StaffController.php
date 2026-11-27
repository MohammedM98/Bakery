<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/** The owner's staff and the permission checkboxes (groups and Arabic labels from User::PERMISSIONS). */
class StaffController extends Controller
{
    public function __invoke(Request $request): array
    {
        return [
            'users' => $request->user()->bakery->staff()->orderByDesc('is_active')->orderBy('name')->get()
                ->map(fn (User $user) => [...$user->only('id', 'name', 'email', 'is_active'), 'permissions' => $user->permissions ?? []]),
            'groups' => collect(User::PERMISSIONS)->map(fn (array $permissions, string $label) => [
                'label' => $label,
                'permissions' => collect($permissions)->map(fn ($text, $key) => ['key' => $key, 'label' => $text])->values(),
            ])->values(),
        ];
    }
}
