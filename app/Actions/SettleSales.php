<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Movement;
use App\Models\Sale;

/**
 * Keeps the per-sale paid / unpaid status in step with the money ledger. A payment pays its
 * sale first (when it names one), then the oldest unpaid sales; anything left is credit (له).
 * After that, if the ledger says the customer owes less than their unpaid sales add up to
 * (credit from an earlier overpayment, or a "له" adjustment), the difference settles the
 * oldest sales too. So unpaid sales never claim more than the money balance. Never un-pays a sale.
 * Call it inside the transaction, after the money movements are written.
 */
class SettleSales
{
    public function handle(Customer $customer, int $paymentCents = 0, ?Sale $first = null): void
    {
        $sales = $customer->sales()
            ->where('payment_status', Sale::STATUS_UNPAID)
            ->oldest('sale_date')->oldest('id')
            ->lockForUpdate()
            ->get()
            ->sortBy(fn (Sale $sale) => $sale->is($first) ? 0 : 1)
            ->values();

        $unpaid = $sales->sum(fn (Sale $sale) => Movement::cents($sale->remainingAmount()));
        $excess = $unpaid - max(0, $customer->balanceCents(Movement::LEDGER_MONEY));
        $remaining = max($paymentCents, $excess);

        foreach ($sales as $sale) {
            if ($remaining <= 0) {
                break;
            }

            $applied = min($remaining, Movement::cents($sale->remainingAmount()));
            $paid = Movement::cents($sale->paid_amount) + $applied;
            $sale->paid_amount = Movement::decimal($paid);

            if ($paid >= Movement::cents($sale->total_amount)) {
                $sale->payment_status = Sale::STATUS_PAID;
                $sale->paid_at = now();
            }

            $sale->save();
            $remaining -= $applied;
        }
    }
}
