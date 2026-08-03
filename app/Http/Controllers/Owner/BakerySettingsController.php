<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BakerySettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $bakery = $request->user()->bakery;

        return view('owner.settings.edit', compact('bakery'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'regular_price_per_kg' => ['required', 'numeric', 'min:0'],
            'flour_exchange_fee_per_kg' => ['required', 'numeric', 'min:0'],
        ]);

        $request->user()->bakery->update($data);

        return back()->with('status', 'تم تحديث أسعار الخبز.');
    }
}
