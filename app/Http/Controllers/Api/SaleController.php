<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\SaleController as WebSales;
use App\Http\Resources\SaleResource;
use Illuminate\Http\Request;

/** The website's sales list: ?type=cash|flour_exchange&status=paid|unpaid&date=Y-m-d&page=, 20 a page. */
class SaleController extends Controller
{
    public function __invoke(Request $request): array
    {
        [$sales, $filters] = WebSales::filtered($request);

        return [
            'sales' => SaleResource::collection($sales)->response()->getData(true),
            'filters' => $filters,
        ];
    }
}
