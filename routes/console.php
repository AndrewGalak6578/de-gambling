<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:grant-admin {email}', function (string $email) {
    Role::updateOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    Role::updateOrCreate(['slug' => 'user'], ['name' => 'User']);

    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("User with email {$email} was not found.");

        return Command::FAILURE;
    }

    $user->assignRole('admin');
    $this->info("Admin role granted to {$email}.");

    return Command::SUCCESS;
})->purpose('Grant the admin role to an existing user by email');
