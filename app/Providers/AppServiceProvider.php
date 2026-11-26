<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // One gate per permission (9-ب item 7). The owner holds all of them; staff hold what the owner ticked;
        // the owner-only ones (delete customers / banks, manage users, settings) are never granted to staff.
        foreach ([...User::permissionKeys(), ...User::OWNER_ONLY] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
    }
}
