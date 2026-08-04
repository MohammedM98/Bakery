<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(): View
    {
        return view('owner.sales.index');
    }

    public function create(): View
    {
        return view('owner.sales.create');
    }
}
