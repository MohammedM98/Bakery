<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\DashboardController as WebDashboard;
use Illuminate\Http\Request;

/** The website dashboard's figures for ?period=day|week|month|year, with display texts. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, WebDashboard $dashboard): array
    {
        $data = $dashboard->data($request);

        return [
            ...$data,
            'bread' => [...$data['bread'], 'kg_text' => kg($data['bread']['kg']), 'regular_text' => kg($data['bread']['regular']), 'flour_text' => kg($data['bread']['flour'])],
            'takings' => [
                ...$data['takings'],
                'received_text' => money($data['takings']['received']),
                'cash_text' => money($data['takings']['cash']),
                'transfer_text' => money($data['takings']['transfer']),
                'unpaid_text' => money($data['takings']['unpaid']),
            ],
            'recent' => array_map(fn (array $row) => [
                ...$row,
                'amount_text' => $row['ledger'] === 'money' ? money(abs((float) $row['amount'])) : kg(abs((float) $row['amount'])),
            ], $data['recent']),
            'price_text' => money($data['pricePerKg']),
        ];
    }
}
