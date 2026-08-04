<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(): View
    {
        return view('owner.sales.index');
    }

    public function create(Request $request): View
    {
        $bakery = $request->user()->bakery;
        $customers = Customer::where('bakery_id', $bakery->id)->orderBy('name')->get();
        $selectedCustomer = $request->integer('customer_id') ?: null;

        return view('owner.sales.create', compact('bakery', 'customers', 'selectedCustomer'));
    }

    public function store(Request $request): RedirectResponse
    {
        $bakery = $request->user()->bakery;

        $data = $request->validate([
            'sale_type' => ['required', Rule::in([Sale::TYPE_CASH, Sale::TYPE_FLOUR_EXCHANGE])],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'kg_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_status' => ['required', Rule::in([Sale::STATUS_PAID, Sale::STATUS_UNPAID])],
            'sale_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Price is never entered at the point of sale — it always comes from the
        // bakery's price settings, so the owner can only change it in one place.
        $pricePerKg = $data['sale_type'] === Sale::TYPE_FLOUR_EXCHANGE
            ? $bakery->flour_exchange_fee_per_kg
            : $bakery->regular_price_per_kg;

        if ($data['sale_type'] === Sale::TYPE_FLOUR_EXCHANGE && empty($data['customer_id'])) {
            throw ValidationException::withMessages([
                'customer_id' => 'يجب اختيار العميل عند التسليم مقابل رصيد القمح.',
            ]);
        }

        $customer = null;
        if (! empty($data['customer_id'])) {
            $customer = Customer::where('bakery_id', $bakery->id)->findOrFail($data['customer_id']);
        }

        if ($data['sale_type'] === Sale::TYPE_FLOUR_EXCHANGE && $customer->flour_balance_kg < $data['kg_amount']) {
            throw ValidationException::withMessages([
                'kg_amount' => 'رصيد القمح لدى العميل غير كافٍ لهذه الكمية.',
            ]);
        }

        DB::transaction(function () use ($data, $bakery, $customer, $pricePerKg, $request) {
            Sale::create([
                'bakery_id' => $bakery->id,
                'customer_id' => $customer?->id,
                'sale_type' => $data['sale_type'],
                'kg_amount' => $data['kg_amount'],
                'price_per_kg' => $pricePerKg,
                'total_amount' => round($data['kg_amount'] * $pricePerKg, 2),
                'payment_status' => $data['payment_status'],
                'paid_at' => $data['payment_status'] === Sale::STATUS_PAID ? now() : null,
                'sale_date' => $data['sale_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            if ($data['sale_type'] === Sale::TYPE_FLOUR_EXCHANGE) {
                $customer->decrement('flour_balance_kg', $data['kg_amount']);
            }
        });

        return redirect()->route('panel.sales.index')->with('status', 'تم تسجيل عملية البيع بنجاح.');
    }
}
