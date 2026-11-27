<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __invoke(Request $request): array
    {
        return ['prices' => MeResource::prices($request->user()->bakery)];
    }
}
