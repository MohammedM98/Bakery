<?php

namespace App\Http\Controllers\Owner;

use App\Actions\CreateCustomer;
use App\Actions\DeleteCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\CustomerRequest;
use App\Models\Customer;
use App\Models\Movement;
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

    public function store(CustomerRequest $request, CreateCustomer $action): RedirectResponse
    {
        $customer = $action->handle($request->user()->bakery, $request->validated(), $request->user());

        return redirect()->route('panel.customers.index')
            ->with('success', "تم حفظ بيانات \"{$customer->name}\" بنجاح.");
    }

    public function show(Request $request, Customer $customer): Response
    {
        $this->authorizeCustomer($request, $customer);

        // A real Y-m-d date or null ("no bound"); anything else falls back to the default range below.
        $valid = fn ($v) => is_string($v) && ($d = \DateTime::createFromFormat('!Y-m-d', $v)) && $d->format('Y-m-d') === $v;
        $date = fn (string $key) => $valid($request->query($key)) ? $request->query($key) : null;
        $explicit = fn (string $key) => $request->has($key) && in_array($request->query($key), [null, ''], true) || $valid($request->query($key));
        // Default: the current month up to today. "All" sends from= empty, so no start date.
        $from = $explicit('from') ? $date('from') : now()->startOfMonth()->toDateString();
        $to = $explicit('to') ? $date('to') : now()->toDateString();

        return Inertia::render('Owner/Customers/Show', [
            'customer' => [
                ...$customer->only('id', 'name', 'mobile_number', 'notes', 'flour_balance_kg'),
                'money_balance' => Movement::decimal($customer->balanceCents(Movement::LEDGER_MONEY)),
            ],
            'flourExchangeFee' => $customer->bakery->flour_exchange_fee_per_kg,
            'range' => ['from' => $from, 'to' => $to],
            'moneyStatement' => $customer->statement(Movement::LEDGER_MONEY, $from, $to),
            'flourStatement' => $customer->statement(Movement::LEDGER_FLOUR, $from, $to),
            'sales' => $customer->sales()->with('customer:id,name')->latest('sale_date')->latest('id')->get()
                ->map(fn (Sale $sale) => $sale->toRow()),
            'canAdjust' => $request->user()->can('record-adjustment'),
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
