<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bakery;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class BakeryController extends Controller
{
    public function index(): View
    {
        $bakeries = Bakery::with('owners')->latest()->paginate(15);

        return view('admin.bakeries.index', compact('bakeries'));
    }

    public function create(): View
    {
        return view('admin.bakeries.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:8'],
            'subscription_months' => ['required', 'integer', 'min:1', 'max:24'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $bakery = Bakery::create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'subscription_status' => Bakery::STATUS_ACTIVE,
                'subscription_expires_at' => now()->addMonths((int) $data['subscription_months'])->toDateString(),
                'regular_price_per_kg' => 0,
                'flour_exchange_fee_per_kg' => 0,
                'created_by' => $request->user()->id,
            ]);

            User::create([
                'bakery_id' => $bakery->id,
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => Hash::make($data['owner_password']),
                'role' => User::ROLE_BAKERY_OWNER,
            ]);
        });

        return redirect()->route('admin.bakeries.index')->with('status', 'تم إنشاء المخبز وحساب المالك بنجاح.');
    }

    public function edit(Bakery $bakery): View
    {
        $bakery->load('owners');

        return view('admin.bakeries.edit', compact('bakery'));
    }

    public function update(Request $request, Bakery $bakery): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $bakery->update($data);

        return redirect()->route('admin.bakeries.index')->with('status', 'تم تحديث بيانات المخبز.');
    }

    public function renew(Request $request, Bakery $bakery): RedirectResponse
    {
        $data = $request->validate([
            'subscription_months' => ['required', 'integer', 'min:1', 'max:24'],
        ]);

        $base = $bakery->subscription_expires_at && $bakery->subscription_expires_at->isFuture()
            ? $bakery->subscription_expires_at
            : now();

        $bakery->update([
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => $base->copy()->addMonths((int) $data['subscription_months'])->toDateString(),
        ]);

        return back()->with('status', 'تم تجديد الاشتراك بنجاح.');
    }

    public function toggleStatus(Bakery $bakery): RedirectResponse
    {
        $bakery->update([
            'subscription_status' => $bakery->subscription_status === Bakery::STATUS_ACTIVE
                ? Bakery::STATUS_INACTIVE
                : Bakery::STATUS_ACTIVE,
        ]);

        return back()->with('status', 'تم تحديث حالة الاشتراك.');
    }

    public function destroy(Bakery $bakery): RedirectResponse
    {
        DB::transaction(function () use ($bakery) {
            $bakery->owners()->delete();
            $bakery->delete();
        });

        return redirect()->route('admin.bakeries.index')->with('status', 'تم حذف المخبز وجميع بياناته.');
    }
}
