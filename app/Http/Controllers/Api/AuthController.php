<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\MeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Token sign-in for the mobile app: the website's login rules (lockout, one generic error, disabled accounts refused). */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticatedUser();

        return response()->json([
            'token' => $user->createToken($request->validated('device_name'))->plainTextToken,
            ...MeResource::make($user)->resolve(),
        ]);
    }

    /** Revokes this device's token only; the user's other devices stay signed in. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['logged_out' => true]);
    }

    public function me(Request $request): MeResource
    {
        return MeResource::make($request->user());
    }
}
