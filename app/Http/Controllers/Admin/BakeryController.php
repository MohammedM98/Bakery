<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateBakeryWithOwner;
use App\Actions\DeleteBakery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RenewSubscriptionRequest;
use App\Http\Requests\Admin\StoreBakeryRequest;
use App\Http\Requests\Admin\UpdateBakeryRequest;
use App\Models\Bakery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BakeryController extends Controller
{
    public function index(): Response
    {
        $bakeries = Bakery::with('owners:id,bakery_id,name,email')
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->through(fn (Bakery $bakery) => [
                ...$this->subscription($bakery),
                'id' => $bakery->id,
                'name' => $bakery->name,
                'phone' => $bakery->phone,
                'owner' => $bakery->owners->first()?->only('name', 'email'),
            ]);

        return Inertia::render('Admin/Bakeries/Index', ['bakeries' => $bakeries]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Bakeries/Create');
    }

    public function store(StoreBakeryRequest $request, CreateBakeryWithOwner $action): RedirectResponse
    {
        $bakery = $action->handle($request->validated(), $request->user());

        return redirect()->route('admin.bakeries.index')
            ->with('success', "تم إنشاء مخبز \"{$bakery->name}\" وحساب صاحبه.");
    }

    public function edit(Bakery $bakery): Response
    {
        return Inertia::render('Admin/Bakeries/Edit', [
            'bakery' => [
                ...$this->subscription($bakery),
                'id' => $bakery->id,
                'name' => $bakery->name,
                'phone' => $bakery->phone,
                'address' => $bakery->address,
            ],
            'owners' => $bakery->owners()->orderBy('id')->get(['name', 'email']),
        ]);
    }

    public function update(UpdateBakeryRequest $request, Bakery $bakery): RedirectResponse
    {
        $bakery->update($request->validated());

        return back()->with('success', 'تم تحديث بيانات المخبز.');
    }

    public function renew(RenewSubscriptionRequest $request, Bakery $bakery): RedirectResponse
    {
        $bakery->renew((int) $request->validated('months'));

        return back()->with('success', "تم تجديد اشتراك \"{$bakery->name}\" بنجاح.");
    }

    public function toggleStatus(Bakery $bakery): RedirectResponse
    {
        $bakery->toggleStatus();

        return back()->with('success', $bakery->subscription_status === Bakery::STATUS_ACTIVE
            ? 'تم تفعيل الاشتراك.'
            : 'تم إيقاف الاشتراك.');
    }

    public function destroy(Bakery $bakery, DeleteBakery $action): RedirectResponse
    {
        $action->handle($bakery);

        return redirect()->route('admin.bakeries.index')
            ->with('success', "تم حذف مخبز \"{$bakery->name}\" وجميع بياناته.");
    }

    /**
     * @return array{subscription_status: string, is_active: bool, expires_at: ?string, days_left: ?int}
     */
    private function subscription(Bakery $bakery): array
    {
        return [
            'subscription_status' => $bakery->subscription_status,
            'is_active' => $bakery->isSubscriptionActive(),
            'expires_at' => $bakery->subscription_expires_at?->toDateString(),
            'days_left' => $bakery->days_until_expiry === null ? null : (int) $bakery->days_until_expiry,
        ];
    }
}
