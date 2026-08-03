<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::where('bakery_id', $request->user()->bakery_id)
            ->when($request->string('search')->trim()->toString(), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('owner.customers.index', compact('customers'));
    }

    public function create(): View
    {
        return view('owner.customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:50'],
            'flour_balance_kg' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = Customer::create([
            'bakery_id' => $request->user()->bakery_id,
            'name' => $data['name'],
            'mobile_number' => $data['mobile_number'],
            'flour_balance_kg' => $data['flour_balance_kg'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('panel.customers.show', $customer)->with('status', 'تم إضافة العميل بنجاح.');
    }

    public function show(Request $request, Customer $customer): View
    {
        abort_unless($customer->bakery_id === $request->user()->bakery_id, 403);

        $customer->load([
            'flourDeposits' => fn ($query) => $query->latest('deposit_date')->latest('id'),
            'sales' => fn ($query) => $query->latest('sale_date')->latest('id'),
        ]);

        return view('owner.customers.show', compact('customer'));
    }

    public function edit(Request $request, Customer $customer): View
    {
        abort_unless($customer->bakery_id === $request->user()->bakery_id, 403);

        return view('owner.customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($customer->bakery_id === $request->user()->bakery_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer->update($data);

        return redirect()->route('panel.customers.show', $customer)->with('status', 'تم تحديث بيانات العميل.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($customer->bakery_id === $request->user()->bakery_id, 403);

        $customer->delete();

        return redirect()->route('panel.customers.index')->with('status', 'تم حذف العميل وجميع سجلاته.');
    }
}
