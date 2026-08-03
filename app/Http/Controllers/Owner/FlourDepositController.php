<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FlourDeposit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlourDepositController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($customer->bakery_id === $request->user()->bakery_id, 403);

        $data = $request->validate([
            'kg_amount' => ['required', 'numeric', 'min:0.01'],
            'deposit_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data, $customer, $request) {
            FlourDeposit::create([
                'bakery_id' => $customer->bakery_id,
                'customer_id' => $customer->id,
                'kg_amount' => $data['kg_amount'],
                'deposit_date' => $data['deposit_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $customer->increment('flour_balance_kg', $data['kg_amount']);
        });

        return back()->with('status', 'تم تسجيل استلام القمح وإضافته لرصيد العميل.');
    }

    public function destroy(Request $request, FlourDeposit $flourDeposit): RedirectResponse
    {
        abort_unless($flourDeposit->bakery_id === $request->user()->bakery_id, 403);

        DB::transaction(function () use ($flourDeposit) {
            $flourDeposit->customer->decrement('flour_balance_kg', $flourDeposit->kg_amount);
            $flourDeposit->delete();
        });

        return back()->with('status', 'تم حذف عملية استلام القمح.');
    }
}
