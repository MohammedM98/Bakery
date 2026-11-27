<?php

namespace App\Http\Controllers\Owner;

use App\Actions\MarkSalePaid;
use App\Actions\RecordSale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreSaleRequest;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        [$sales, $filters] = self::filtered($request);

        return Inertia::render('Owner/Sales/Index', [
            'sales' => $sales->through(fn (Sale $sale) => $sale->toRow()),
            'filters' => $filters,
            'pricePerKg' => $request->user()->bakery->regular_price_per_kg,
        ]);
    }

    /**
     * The list's page of sales (customer loaded) and its filters; the mobile API's list uses the same ones.
     *
     * @return array{0: LengthAwarePaginator, 1: array{type: string, status: string, date: string}}
     */
    public static function filtered(Request $request): array
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
            ->withQueryString();

        return [$sales, ['type' => $type, 'status' => $status, 'date' => $date]];
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Owner/Sales/Create', [
            'pricePerKg' => $request->user()->bakery->regular_price_per_kg,
        ]);
    }

    /** Cash sale by the kilo, to a walk-in buyer (name and mobile optional). */
    public function store(StoreSaleRequest $request, RecordSale $action): RedirectResponse
    {
        $data = $request->validated();
        $bakery = $request->user()->bakery;

        $request->once(fn () => $action->handle($bakery, null, Sale::TYPE_CASH, $data, $request->user()));

        return back()->with('success', 'تم تسجيل عملية البيع بنجاح.');
    }

    /** Confirms the rest of the sale as received, as a payment movement. Final: there is no undo. */
    public function markPaid(Request $request, Sale $sale, MarkSalePaid $action): RedirectResponse
    {
        abort_unless($sale->bakery_id === $request->user()->bakery_id, 404);

        $remaining = $action->handle($sale, $request->user());

        if ($remaining === null) {
            return back()->with('error', 'هذه العملية مدفوعة مسبقًا.');
        }

        return back()->with('success', 'تم تأكيد استلام المتبقي وقدره '.money($remaining).'. لا يمكن التراجع عن هذا الإجراء.');
    }
}
