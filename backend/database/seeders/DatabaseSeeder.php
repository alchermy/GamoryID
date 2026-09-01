<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::updateOrCreate(['code' => 'starter'], [
            'name' => 'Starter',
            'active_inventory_limit' => 1000,
            'member_limit' => 3,
            'price_thb' => 299,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        SubscriptionPlan::updateOrCreate(['code' => 'growth'], [
            'name' => 'Growth',
            'active_inventory_limit' => 5000,
            'member_limit' => 8,
            'price_thb' => 699,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        User::updateOrCreate(
            ['email' => 'admin@gamoryid.local'],
            ['name' => 'GamoryID Admin', 'password' => 'password'],
        )->forceFill(['is_super_admin' => true])->save();
    }
}
