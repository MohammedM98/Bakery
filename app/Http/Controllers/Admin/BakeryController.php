<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bakery;
use Illuminate\View\View;

class BakeryController extends Controller
{
    public function index(): View
    {
        return view('admin.bakeries.index');
    }

    public function create(): View
    {
        return view('admin.bakeries.create');
    }

    public function edit(Bakery $bakery): View
    {
        return view('admin.bakeries.edit', compact('bakery'));
    }
}
