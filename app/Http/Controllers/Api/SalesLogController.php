<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\SalesLogController as WebSalesLog;
use App\Http\Requests\Owner\SalesLogRequest;

/** The website sales log (same filters, cards and paged movements), with display texts. */
class SalesLogController extends Controller
{
    public function __invoke(SalesLogRequest $request, WebSalesLog $log): array
    {
        $data = $log->data($request);
        $summary = $data['summary'];

        return [
            ...$data,
            'summary' => [
                ...$summary,
                ...collect(['bread_kg', 'bread_regular_kg', 'bread_flour_kg', 'flour_spent_kg', 'flour_received_kg'])
                    ->mapWithKeys(fn ($key) => [$key.'_text' => kg($summary[$key])]),
                ...collect(['money_received', 'money_cash', 'money_transfer'])
                    ->mapWithKeys(fn ($key) => [$key.'_text' => money($summary[$key])]),
            ],
            'movements' => $data['movements']->through(fn (array $row) => [
                ...$row,
                'direction_label' => (float) $row['amount'] >= 0 ? 'عليه' : 'له',
                'amount_text' => $row['ledger'] === 'money' ? money(abs((float) $row['amount'])) : kg(abs((float) $row['amount'])),
            ]),
        ];
    }
}
