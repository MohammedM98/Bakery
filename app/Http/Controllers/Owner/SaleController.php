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
    public function index(Request $request): View
    {
        $bakery = $request->user()->bakery;

        $sales = Sale::where('bakery_id', $bakery->id)
            ->with('customer')
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('payment_status', $status))
            ->when($request->string('date')->toString(), fn ($query, $date) => $query->where('sale_date', $date))
            ->latest('sale_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('owner.sales.index', compact('sales'));
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
            'price_per_kg' => ['required', 'numeric', 'min:0'],
            'payment_status' => ['required', Rule::in([Sale::STATUS_PAID, Sale::STATUS_UNPAID])],
            'sale_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

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

        DB::transaction(function () use ($data, $bakery, $customer, $request) {
            Sale::create([
                'bakery_id' => $bakery->id,
                'customer_id' => $customer?->id,
                'sale_type' => $data['sale_type'],
                'kg_amount' => $data['kg_amount'],
                'price_per_kg' => $data['price_per_kg'],
                'total_amount' => round($data['kg_amount'] * $data['price_per_kg'], 2),
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

    public function markPaid(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless($sale->bakery_id === $request->user()->bakery_id, 403);

        $sale->update(['payment_status' => Sale::STATUS_PAID, 'paid_at' => now()]);

        return back()->with('status', "تم تأكيد استلام الدفع لهذه العملية بمبلغ {$sale->total_amount}.");
    }

    public function markUnpaid(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless($sale->bakery_id === $request->user()->bakery_id, 403);

        $sale->update(['payment_status' => Sale::STATUS_UNPAID, 'paid_at' => null]);

        return back()->with('status', 'تم تحويل حالة العملية إلى غير مدفوعة.');
    }

    public function destroy(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless($sale->bakery_id === $request->user()->bakery_id, 403);

        DB::transaction(function () use ($sale) {
            if ($sale->isFlourExchange() && $sale->customer) {
                $sale->customer->increment('flour_balance_kg', $sale->kg_amount);
            }

            $sale->delete();
        });

        return back()->with('status', 'تم حذف عملية البيع.');
    }
}
