<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreSaleRequest;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        // Unknown filter values fall back to the defaults instead of erroring.
        $type = $request->query('type') === Sale::TYPE_FLOUR_EXCHANGE ? Sale::TYPE_FLOUR_EXCHANGE : Sale::TYPE_CASH;
        $status = in_array($request->query('status'), [Sale::STATUS_PAID, Sale::STATUS_UNPAID], true) ? $request->query('status') : '';
        $date = is_string($request->query('date')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->query('date')) ? $request->query('date') : '';

        $sales = $request->user()->bakery->sales()
            ->with('customer:id,name')
            ->where('sale_type', $type)
            ->when($status, fn ($query) => $query->where('payment_status', $status))
            ->when($date, fn ($query) => $query->whereDate('sale_date', $date))
            ->latest('sale_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Sale $sale) => $sale->toRow());

        return Inertia::render('Owner/Sales/Index', [
            'sales' => $sales,
            'filters' => ['type' => $type, 'status' => $status, 'date' => $date],
            'pricePerKg' => $request->user()->bakery->regular_price_per_kg,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Owner/Sales/Create', [
            'pricePerKg' => $request->user()->bakery->regular_price_per_kg,
        ]);
    }

    /** Cash sale by the kilo, to a walk-in buyer (name and mobile optional). */
    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $bakery = $request->user()->bakery;

        $request->once(function () use ($data, $bakery, $request) {
            $total = round($data['kg_amount'] * $bakery->regular_price_per_kg, 2);
            $paid = $data['payment_status'] === Sale::STATUS_PAID;

            $bakery->sales()->create([
                'buyer_name' => $data['buyer_name'] ?? null,
                'buyer_mobile' => $data['buyer_mobile'] ?? null,
                'sale_type' => Sale::TYPE_CASH,
                'kg_amount' => $data['kg_amount'],
                'price_per_kg' => $bakery->regular_price_per_kg,
                'total_amount' => $total,
                'paid_amount' => $paid ? $total : 0,
                'payment_status' => $data['payment_status'],
                'paid_at' => $paid ? now() : null,
                'sale_date' => $data['sale_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'تم تسجيل عملية البيع بنجاح.');
    }

    /** Confirms the rest of the sale as received. Final: there is no undo. */
    public function markPaid(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless($sale->bakery_id === $request->user()->bakery_id, 404);

        $remaining = $sale->remainingAmount();

        // Conditional update: a second click (or tab) finds nothing left to update.
        $updated = $request->user()->bakery->sales()->whereKey($sale->id)
            ->where('payment_status', Sale::STATUS_UNPAID)
            ->update(['payment_status' => Sale::STATUS_PAID, 'paid_amount' => $sale->total_amount, 'paid_at' => now()]);

        if (! $updated) {
            return back()->with('error', 'هذه العملية مدفوعة مسبقًا.');
        }

        return back()->with('success', 'تم تأكيد استلام المتبقي وقدره '.money($remaining).'. لا يمكن التراجع عن هذا الإجراء.');
    }
}
