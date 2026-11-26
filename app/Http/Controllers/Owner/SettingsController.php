<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\UpdateSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $bakery = $request->user()->bakery;

        return Inertia::render('Owner/Settings/Edit', [
            'settings' => [
                'regular_price_per_kg' => $bakery->regular_price_per_kg,
                'flour_exchange_fee_per_kg' => $bakery->flour_exchange_fee_per_kg,
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $request->user()->bakery->update($request->validated());

        return back()->with('success', 'تم تحديث أسعار الخبز.');
    }
}
