<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StaffRequest;
use App\Http\Requests\Owner\UpdateStaffStatusRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The bakery owner's staff accounts and their checkbox permissions (9-ب item 7). Owner only (route gate manage-users). */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Owner/Users/Index', [
            'users' => $request->user()->bakery->staff()->orderByDesc('is_active')->orderBy('name')->get()
                ->map(fn (User $user) => [
                    ...$user->only('id', 'name', 'email', 'is_active'),
                    'permissions_count' => count($user->permissions ?? []),
                ]),
            'permissionsTotal' => count(User::permissionKeys()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Owner/Users/Create', ['groups' => $this->groups()]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $user = $request->user()->bakery->users()->create([
            ...$request->safe()->only('name', 'email', 'password', 'permissions'),
            'role' => User::ROLE_BAKERY_STAFF,
            'is_active' => true,
        ]);

        return redirect()->route('panel.users.index')->with('success', "تمت إضافة \"{$user->name}\".");
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorizeStaff($request, $user);

        return Inertia::render('Owner/Users/Edit', [
            'user' => [...$user->only('id', 'name', 'email', 'is_active'), 'permissions' => $user->permissions ?? []],
            'groups' => $this->groups(),
        ]);
    }

    public function update(StaffRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->only('name', 'email', 'permissions');
        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $user->update($data);

        return redirect()->route('panel.users.index')->with('success', "تم حفظ بيانات \"{$user->name}\" وصلاحياته.");
    }

    /** Sends the wanted state (not a toggle), so a double click cannot undo itself. */
    public function updateStatus(UpdateStaffStatusRequest $request, User $user): RedirectResponse
    {
        $active = $request->boolean('active');

        $user->update(['is_active' => $active]);

        return back()->with('success', $active ? "تم تفعيل حساب \"{$user->name}\"." : "تم إيقاف حساب \"{$user->name}\". لن يستطيع الدخول.");
    }

    private function authorizeStaff(Request $request, User $user): void
    {
        abort_unless($user->isBakeryStaff() && $user->bakery_id === $request->user()->bakery_id, 404);
    }

    /** @return list<array{label: string, permissions: list<array{key: string, label: string}>}> */
    private function groups(): array
    {
        return collect(User::PERMISSIONS)->map(fn (array $permissions, string $label) => [
            'label' => $label,
            'permissions' => collect($permissions)->map(fn ($text, $key) => ['key' => $key, 'label' => $text])->values()->all(),
        ])->values()->all();
    }
}
