<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeRedirectController extends Controller
{
    /**
     * Send the authenticated user to the panel that matches their role.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->role === User::ROLE_SUPER_ADMIN) {
            return redirect()->route('admin.bakeries.index');
        }

        return redirect()->route('panel.dashboard');
    }
}
