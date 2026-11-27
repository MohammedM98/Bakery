<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StatementRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Models\Movement;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    /** ?search= (name or mobile), 30 a page, each with both balances. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search', ''));

        return CustomerResource::collection($request->user()->bakery->customers()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")->orWhere('mobile_number', 'like', "%{$search}%")))
            ->withSum(['movements as money_balance' => fn ($query) => $query->where('ledger', Movement::LEDGER_MONEY)], 'amount')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString());
    }

    /**
     * The website's customer page: balances, both statements for ?from=&to= (default: this month), sales,
     * and what the payment form needs. Each part only with its permission, as on the website.
     */
    public function show(StatementRequest $request, int $customer): array
    {
        $customer = $request->user()->bakery->customers()->findOrFail($customer);
        ['from' => $from, 'to' => $to] = $request->range();
        $user = $request->user();
        $sales = fn ($query) => SaleResource::collection($query->with('customer:id,name')->latest('sale_date')->latest('id')->get())->resolve();

        return [
            'customer' => CustomerResource::make($customer)->resolve(),
            'flour_exchange_fee' => $customer->bakery->flour_exchange_fee_per_kg,
            'range' => ['from' => $from, 'to' => $to],
            'money_statement' => $user->can('view-money-statement') ? $this->statement($customer, Movement::LEDGER_MONEY, $from, $to) : null,
            'flour_statement' => $user->can('view-flour-statement') ? $this->statement($customer, Movement::LEDGER_FLOUR, $from, $to) : null,
            'sales' => $user->can('view-sales') ? $sales($customer->sales()) : null,
            'unpaid_sales' => $user->can('record-payment') ? $sales($customer->sales()->where('payment_status', Sale::STATUS_UNPAID)) : [],
            'banks' => $user->can('record-payment') ? $customer->bakery->banks()->orderBy('name')->get(['id', 'name']) : [],
            'can_adjust' => $user->can('record-adjustment'),
        ];
    }

    /** Customer::statement with texts: money by sign, flour balances in words, row amounts without a sign. */
    private function statement(Customer $customer, string $ledger, ?string $from, ?string $to): array
    {
        $statement = $customer->statement($ledger, $from, $to);
        $balance = fn (string $value) => $ledger === Movement::LEDGER_MONEY ? money($value) : flour_balance($value);
        $size = fn (?string $value) => $value === null ? null : ($ledger === Movement::LEDGER_MONEY ? money($value) : kg($value));

        return [
            'opening' => $statement['opening'],
            'opening_text' => $balance($statement['opening']),
            'closing' => $statement['closing'],
            'closing_text' => $balance($statement['closing']),
            'rows' => array_map(fn (array $row) => [
                ...$row,
                'debit_text' => $size($row['debit']),
                'credit_text' => $size($row['credit']),
                'balance_text' => $balance($row['balance']),
            ], $statement['rows']),
        ];
    }
}
