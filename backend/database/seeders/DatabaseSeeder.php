<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::syncDefaults();

        User::updateOrCreate(
            ['email' => 'admin@gamoryid.com'],
            ['name' => 'GamoryID Admin', 'password' => 'password'],
        )->forceFill(['is_super_admin' => true])->save();
    }
}
