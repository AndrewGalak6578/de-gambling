<?php

namespace Tests\Support;

use App\Models\Role;
use App\Models\User;
use App\Modules\Finance\Contracts\PaymentGatewayInterface;

trait CreatesFinanceFixtures
{
    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function fakePaymentGateway(?FakePaymentGateway $gateway = null): FakePaymentGateway
    {
        $gateway ??= new FakePaymentGateway();
        $this->app->instance(PaymentGatewayInterface::class, $gateway);

        return $gateway;
    }
}
