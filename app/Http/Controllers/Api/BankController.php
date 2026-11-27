<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function __invoke(Request $request): array
    {
        return [
            'banks' => $request->user()->bakery->banks()->orderBy('name')->get(['id', 'name']),
            'can_manage' => $request->user()->can('manage-banks'),
            'can_delete' => $request->user()->can('delete-banks'),
        ];
    }
}
