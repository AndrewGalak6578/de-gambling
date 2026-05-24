<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userRole = Role::where('slug', 'user')->first();

        if (! $userRole) {
            return;
        }

        User::whereDoesntHave('roles')->each(
            fn (User $user) => $user->roles()->syncWithoutDetaching([$userRole->id]),
        );
    }
}
