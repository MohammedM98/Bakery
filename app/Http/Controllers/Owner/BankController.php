<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\BankRequest;
use App\Models\Bank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The bakery's banks and e-wallets: one list for both sides of a transfer. */
class BankController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('view-banks');

        return Inertia::render('Owner/Banks/Index', [
            'banks' => $request->user()->bakery->banks()->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->can('manage-banks'),
            'canDelete' => $request->user()->can('delete-banks'),
        ]);
    }

    public function store(BankRequest $request): RedirectResponse
    {
        $bank = $request->user()->bakery->banks()->create($request->validated());

        return back()->with('success', "تمت إضافة \"{$bank->name}\".");
    }

    public function update(BankRequest $request, Bank $bank): RedirectResponse
    {
        $this->authorizeBank($request, $bank);

        $bank->update($request->validated());

        return back()->with('success', "تم حفظ الاسم \"{$bank->name}\".");
    }

    /** Permanent. Old payments keep the name: it is stored as text on each movement. */
    public function destroy(Request $request, Bank $bank): RedirectResponse
    {
        $this->authorizeBank($request, $bank);
        Gate::authorize('delete-banks');

        $bank->delete();

        return back()->with('success', "تم حذف \"{$bank->name}\". الدفعات المسجلة به تبقى كما هي في الكشوف.");
    }

    private function authorizeBank(Request $request, Bank $bank): void
    {
        abort_unless($bank->bakery_id === $request->user()->bakery_id, 404);
    }
}
