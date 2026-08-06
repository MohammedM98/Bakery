<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        return view('owner.customers.index');
    }

    public function create(): View
    {
        return view('owner.customers.create');
    }

    public function show(Request $request, Customer $customer): View
    {
        abort_unless($customer->bakery_id === $request->user()->bakery_id, 403);

        $outstandingBalance = $customer->outstandingBalance();

        return view('owner.customers.show', compact('customer', 'outstandingBalance'));
    }

    public function edit(Request $request, Customer $customer): View
    {
        abort_unless($customer->bakery_id === $request->user()->bakery_id, 403);

        return view('owner.customers.edit', compact('customer'));
    }
}
