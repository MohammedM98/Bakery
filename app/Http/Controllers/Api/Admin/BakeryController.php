<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BakeryResource;
use App\Models\Bakery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** The platform owner's bakeries with their subscription state and owner account. */
class BakeryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BakeryResource::collection(Bakery::with('owners:id,bakery_id,name,email')->latest()->latest('id')->paginate(15));
    }

    public function show(Bakery $bakery): array
    {
        return ['bakery' => BakeryResource::make($bakery->load('owners:id,bakery_id,name,email'))->resolve()];
    }
}
