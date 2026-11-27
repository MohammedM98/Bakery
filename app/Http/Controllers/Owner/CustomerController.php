<?php

namespace App\Http\Controllers\Owner;

use App\Actions\CreateCustomer;
use App\Actions\DeleteCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\CustomerRequest;
use App\Http\Requests\Owner\StatementRequest;
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
            // Notes only for the edit dialog.
            ->paginate(15, ['id', 'name', 'mobile_number', ...($request->user()->can('edit-customers') ? ['notes'] : []), 'flour_balance_kg'])
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
        // A double tap or retried request saves the customer (and its opening balance) once.
        $request->once(fn () => $action->handle($request->user()->bakery, $request->validated(), $request->user()));
        $name = $request->validated('name');

        // Staff without the list stay on the form, ready for the next customer.
        return redirect()->route($request->user()->can('view-customers') ? 'panel.customers.index' : 'panel.customers.create')
            ->with('success', "تم حفظ بيانات \"{$name}\" بنجاح.");
    }

    public function show(StatementRequest $request, Customer $customer): Response
    {
        $this->authorizeCustomer($request, $customer);

        ['from' => $from, 'to' => $to] = $request->range();

        $user = $request->user();

        // Each tab and its data only with its permission; the balances come with the customer.
        return Inertia::render('Owner/Customers/Show', [
            'customer' => [
                ...$customer->only('id', 'name', 'mobile_number', 'notes', 'flour_balance_kg'),
                'money_balance' => Movement::decimal($customer->balanceCents(Movement::LEDGER_MONEY)),
            ],
            'flourExchangeFee' => $customer->bakery->flour_exchange_fee_per_kg,
            'range' => ['from' => $from, 'to' => $to],
            'moneyStatement' => $user->can('view-money-statement') ? $customer->statement(Movement::LEDGER_MONEY, $from, $to) : null,
            'flourStatement' => $user->can('view-flour-statement') ? $customer->statement(Movement::LEDGER_FLOUR, $from, $to) : null,
            'sales' => $user->can('view-sales')
                ? $customer->sales()->with('customer:id,name')->latest('sale_date')->latest('id')->get()->map(fn (Sale $sale) => $sale->toRow())
                : null,
            // The payment dialog needs the unpaid sales and the bank names even without "عرض المبيعات" / "عرض البنوك".
            'unpaidSales' => $user->can('record-payment')
                ? $customer->sales()->with('customer:id,name')->where('payment_status', Sale::STATUS_UNPAID)->latest('sale_date')->latest('id')->get()->map(fn (Sale $sale) => $sale->toRow())
                : [],
            'banks' => $user->can('record-payment') ? $customer->bakery->banks()->orderBy('name')->get(['id', 'name']) : [],
            'canAdjust' => $user->can('record-adjustment'),
            'canExport' => $user->can('export-statements'),
        ]);
    }

    public function edit(Request $request, Customer $customer): Response
    {
        $this->authorizeCustomer($request, $customer);

        return Inertia::render('Owner/Customers/Edit', [
            'customer' => $customer->only('id', 'name', 'mobile_number', 'notes'),
            'canDelete' => $request->user()->can('delete-customers'),
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

        // The full edit page returns to the list, as before; the edit dialog stays where it was opened.
        $fromEditPage = url()->previous() === route('panel.customers.edit', $customer);
        if ($fromEditPage && $request->user()->can('view-customers')) {
            return redirect()->route('panel.customers.index')->with('success', "تم حفظ بيانات \"{$customer->name}\" بنجاح.");
        }

        return back()->with('success', "تم حفظ بيانات \"{$customer->name}\" بنجاح.");
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
