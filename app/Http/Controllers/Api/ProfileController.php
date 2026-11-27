<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\MeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** The website's profile page for every role: name and email (ProfileUpdateRequest), and the password. */
class ProfileController extends Controller
{
    public function update(ProfileUpdateRequest $request): MeResource
    {
        $user = $request->user()->fill($request->validated());
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();

        return MeResource::make($user);
    }

    /**
     * Same rules as the website. A new password revokes every token of the user (User::updated), this device's too,
     * so the answer carries a new token for it: the other devices are signed out, this one stays in.
     */
    public function password(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', Password::defaults(), 'confirmed'],
            'device_name' => ['required', 'string', 'max:255'],
        ], [], ['current_password' => 'كلمة المرور الحالية', 'password' => 'كلمة المرور الجديدة', 'device_name' => 'اسم الجهاز']);

        $user = $request->user();
        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['token' => $user->createToken($validated['device_name'])->plainTextToken]);
    }
}
