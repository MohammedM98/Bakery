<?php

namespace App\Http\Controllers\Owner;

use App\Actions\DeleteCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\CustomerRequest;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $customers = $request->user()->bakery->customers()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15, ['id', 'name', 'mobile_number', 'flour_balance_kg'])
            ->withQueryString();

        return Inertia::render('Owner/Customers/Index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Owner/Customers/Create');
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $customer = $request->user()->bakery->customers()->create([
            'name' => $data['name'],
            'mobile_number' => $data['mobile_number'],
            'flour_balance_kg' => $data['flour_balance_kg'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('panel.customers.index')
            ->with('success', "تم حفظ بيانات \"{$customer->name}\" بنجاح.");
    }

    public function show(Request $request, Customer $customer): Response
    {
        $this->authorizeCustomer($request, $customer);

        return Inertia::render('Owner/Customers/Show', [
            'customer' => $customer->only('id', 'name', 'mobile_number', 'notes', 'flour_balance_kg'),
            'outstandingBalance' => number_format($customer->outstandingBalance(), 2, '.', ''),
            'flourExchangeFee' => $customer->bakery->flour_exchange_fee_per_kg,
            'sales' => $customer->sales()->with('customer:id,name')->latest('sale_date')->latest('id')->get()
                ->map(fn (Sale $sale) => $sale->toRow()),
            'deposits' => $customer->flourDeposits()->latest('deposit_date')->latest('id')
                ->get(['id', 'deposit_date', 'kg_amount', 'notes'])
                ->map(fn ($d) => [...$d->only('id', 'kg_amount', 'notes'), 'deposit_date' => $d->deposit_date->toDateString()]),
            'payments' => $customer->payments()->latest('payment_date')->latest('id')
                ->get(['id', 'payment_date', 'amount', 'notes'])
                ->map(fn ($p) => [...$p->only('id', 'amount', 'notes'), 'payment_date' => $p->payment_date->toDateString()]),
        ]);
    }

    public function edit(Request $request, Customer $customer): Response
    {
        $this->authorizeCustomer($request, $customer);

        return Inertia::render('Owner/Customers/Edit', [
            'customer' => $customer->only('id', 'name', 'mobile_number', 'notes'),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorizeCustomer($request, $customer);

        $data = $request->validated();

        $customer->update([
            'name' => $data['name'],
            'mobile_number' => $data['mobile_number'],
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('panel.customers.index')
            ->with('success', "تم حفظ بيانات \"{$customer->name}\" بنجاح.");
    }

    public function destroy(Request $request, Customer $customer, DeleteCustomer $action): RedirectResponse
    {
        $this->authorizeCustomer($request, $customer);

        $action->handle($customer);

        return redirect()->route('panel.customers.index')
            ->with('success', 'تم حذف العميل وجميع سجلاته.');
    }

    private function authorizeCustomer(Request $request, Customer $customer): void
    {
        abort_unless($customer->bakery_id === $request->user()->bakery_id, 404);
    }
}
