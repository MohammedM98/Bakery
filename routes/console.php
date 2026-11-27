<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// First super admin of a real installation, from ADMIN_EMAIL / ADMIN_PASSWORD (docker-entrypoint.sh). Does nothing once one exists.
// getenv() because env() returns null once the config is cached.
Artisan::command('app:create-admin', function () {
    if (User::where('role', User::ROLE_SUPER_ADMIN)->exists()) {
        return $this->info('A super admin already exists; nothing to do.');
    }

    $data = ['email' => Str::lower((string) getenv('ADMIN_EMAIL')), 'password' => (string) getenv('ADMIN_PASSWORD')];
    $validator = Validator::make($data, ['email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'string', 'min:10']]);
    if ($validator->fails()) {
        $this->error(implode(' ', $validator->errors()->all()));

        return 1;
    }

    User::create(['name' => 'مالك المنصة', ...$data, 'password' => Hash::make($data['password']), 'role' => User::ROLE_SUPER_ADMIN]);
    $this->info("Super admin {$data['email']} created.");
})->purpose('Create the first super admin from ADMIN_EMAIL and ADMIN_PASSWORD');
