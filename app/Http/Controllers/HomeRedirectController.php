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

        if ($user->isBakeryStaff()) {
            // The first page the staff member may open, so a missing dashboard permission is never a 403.
            foreach (self::STAFF_PAGES as $permission => $page) {
                if ($user->can($permission)) {
                    return redirect()->route($page);
                }
            }

            return redirect()->route('panel.no-permissions');
        }

        return redirect()->route('panel.dashboard');
    }

    /** In sidebar order. */
    private const STAFF_PAGES = [
        'view-dashboard' => 'panel.dashboard',
        'view-customers' => 'panel.customers.index',
        'view-sales' => 'panel.sales.index',
        'record-sale' => 'panel.sales.create',
        'view-sales-log' => 'panel.sales-log',
        'view-banks' => 'panel.banks.index',
    ];
}
