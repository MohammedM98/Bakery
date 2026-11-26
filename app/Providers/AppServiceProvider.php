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

        // Owner only for now; session 6 turns each into its staff permission (section 9-ب item 7).
        Gate::define('view-banks', fn (User $user) => $user->isBakeryOwner()); // عرض البنوك
        Gate::define('manage-banks', fn (User $user) => $user->isBakeryOwner()); // إضافة وتعديل بنك
        Gate::define('export-statements', fn (User $user) => $user->isBakeryOwner()); // تحميل الكشوف
        Gate::define('view-sales-log', fn (User $user) => $user->isBakeryOwner()); // عرض سجل مبيعات المخبز
        // Stays owner only: never granted to staff.
        Gate::define('delete-banks', fn (User $user) => $user->isBakeryOwner());
    }
}
