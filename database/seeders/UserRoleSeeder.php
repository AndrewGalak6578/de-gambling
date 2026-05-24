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
        $adminRole = Role::where('slug', 'admin')->first();
        $userRole = Role::where('slug', 'user')->first();

        $adminUser = User::where('email', 'pranav@test.com')->first();

        if ($adminUser && $adminRole) {
            $adminUser->roles()->attach($adminRole->id);
        }

        $users = User::where('email', '!=', 'pranav@test.com')->get();

        foreach ($users as $user) {
            if ($userRole) {
                $user->roles()->attach($userRole->id);
            }
        }
    }
}