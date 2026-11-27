<?php

namespace App\Http\Controllers\Api;

use App\Http\Api\Operations;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/** Every online write: the route names the operation, the URL's ids join the body (and win over it). */
class OperationController extends Controller
{
    public function __invoke(Request $request, Operations $operations): JsonResponse
    {
        $route = $request->route();

        [$status, $body, $replayed] = $operations->run(
            $request->user(),
            $route->defaults['operation'],
            [...$request->all(), ...Arr::except($route->parameters(), 'operation')],
        );

        return response()->json($body, $status)->header('Idempotent-Replayed', $replayed ? 'true' : 'false');
    }
}
