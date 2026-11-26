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
        // Owner only for now; session 6 turns this into the staff permission "تسجيل قيد تسوية".
        Gate::define('record-adjustment', fn (User $user) => $user->isBakeryOwner());
    }
}
